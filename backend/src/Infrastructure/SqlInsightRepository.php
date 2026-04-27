<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Infrastructure;

use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightId;
use DaemsModule\Insights\Domain\InsightRepositoryInterface;
use Daems\Domain\Locale\SupportedLocale;
use Daems\Domain\Locale\TranslationMap;
use Daems\Domain\Tenant\TenantId;
use Daems\Infrastructure\Framework\Database\Connection;

final class SqlInsightRepository implements InsightRepositoryInterface
{
    public function __construct(private readonly Connection $db) {}

    public function listForTenant(TenantId $tenantId, ?string $category = null, bool $includeUnpublished = false): array
    {
        $where  = 'tenant_id = ?';
        $params = [$tenantId->value()];
        if ($category !== null) {
            $where .= ' AND category = ?';
            $params[] = $category;
        }
        if (!$includeUnpublished) {
            $where .= ' AND published_date <= NOW()';
        }
        $rows = $this->db->query(
            "SELECT * FROM insights WHERE {$where} ORDER BY published_date DESC",
            $params,
        );
        return array_map($this->hydrate(...), $rows);
    }

    public function findBySlugForTenant(string $slug, TenantId $tenantId): ?Insight
    {
        $row = $this->db->queryOne(
            'SELECT * FROM insights WHERE slug = ? AND tenant_id = ?',
            [$slug, $tenantId->value()],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByIdForTenant(InsightId $id, TenantId $tenantId): ?Insight
    {
        $row = $this->db->queryOne(
            'SELECT * FROM insights WHERE id = ? AND tenant_id = ? LIMIT 1',
            [$id->value(), $tenantId->value()],
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function delete(InsightId $id, TenantId $tenantId): void
    {
        // CASCADE on insights_i18n.fk_insights_i18n_insight removes the
        // translation rows automatically.
        $this->db->execute(
            'DELETE FROM insights WHERE id = ? AND tenant_id = ?',
            [$id->value(), $tenantId->value()],
        );
    }

    public function save(Insight $insight): void
    {
        // search_text is fi_FI-derived plain text. Pull from the supplied
        // translation map's UI_DEFAULT row, falling back to the legacy
        // scalar content() accessor so older callers keep working.
        $fiRow         = $insight->translations()->rowFor(SupportedLocale::uiDefault()) ?? [];
        $contentForSearch = isset($fiRow['content']) && is_string($fiRow['content']) && trim($fiRow['content']) !== ''
            ? $fiRow['content']
            : $insight->content();
        $searchText = trim((string) preg_replace('/\s+/', ' ', strip_tags($contentForSearch)));

        $this->db->execute(
            'INSERT INTO insights
                (id, tenant_id, slug, category, category_label, featured, published_date,
                 author, reading_time, hero_image, tags_json, search_text)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                category       = VALUES(category),
                category_label = VALUES(category_label),
                featured       = VALUES(featured),
                published_date = VALUES(published_date),
                author         = VALUES(author),
                reading_time   = VALUES(reading_time),
                hero_image     = VALUES(hero_image),
                tags_json      = VALUES(tags_json),
                search_text    = VALUES(search_text)',
            [
                $insight->id()->value(),
                $insight->tenantId()->value(),
                $insight->slug(),
                $insight->category(),
                $insight->categoryLabel(),
                $insight->featured() ? 1 : 0,
                $insight->date(),
                $insight->author(),
                $insight->readingTime(),
                $insight->heroImage(),
                json_encode($insight->tags()),
                $searchText,
            ],
        );

        // Persist any translation rows attached to the entity. On create
        // this writes the seed fi_FI row; on subsequent saves it preserves
        // every locale present in the map. Locales without a row are
        // intentionally skipped so we never blank existing translations.
        foreach ($insight->translations()->raw() as $locale => $row) {
            if ($row === null || !SupportedLocale::isSupported($locale)) {
                continue;
            }
            $this->upsertTranslationRow($insight->id()->value(), $locale, $row);
        }
    }

    public function saveTranslation(
        TenantId $tenantId,
        string $insightId,
        SupportedLocale $locale,
        array $fields,
    ): void {
        $exists = $this->db->queryOne(
            'SELECT 1 FROM insights WHERE id = ? AND tenant_id = ?',
            [$insightId, $tenantId->value()],
        );
        if ($exists === null) {
            throw new \DomainException('insight_not_found_in_tenant');
        }
        $title   = (string) ($fields['title']   ?? '');
        $excerpt = (string) ($fields['excerpt'] ?? '');
        $content = (string) ($fields['content'] ?? '');
        $this->upsertTranslationRow($insightId, $locale->value(), [
            'title'   => $title,
            'excerpt' => $excerpt,
            'content' => $content,
        ]);

        // When the saved locale is fi_FI we also refresh the convenience
        // search_text (used by SqlSearchRepository::searchInsights as a
        // plain-text fallback). Other locales leave search_text untouched.
        if ($locale->value() === SupportedLocale::UI_DEFAULT) {
            $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags($content)));
            $this->db->execute(
                'UPDATE insights SET search_text = ? WHERE id = ? AND tenant_id = ?',
                [$plain, $insightId, $tenantId->value()],
            );
        }
    }

    /**
     * @return array{
     *   published: array{value: int, sparkline: list<array{date: string, value: int}>},
     *   scheduled: array{value: int, sparkline: list<array{date: string, value: int}>},
     *   featured:  array{
     *     value: int,
     *     sparkline: list<array{date: string, value: int}>,
     *     sparkline_scheduled: list<array{date: string, value: int}>
     *   }
     * }
     */
    public function statsForTenant(TenantId $tenantId): array
    {
        // $base drives sparkline key construction (PHP clock).
        // SQL date comparisons use CURDATE() directly so MySQL's clock is
        // authoritative for published/scheduled boundaries. A sub-second
        // timezone skew between PHP and MySQL could shift sparkline bucket
        // labels vs. SQL counts, but both run on the same host in practice.
        $base  = new \DateTimeImmutable('today');
        $today = $base->format('Y-m-d');

        // Build zero-filled date templates: 30 entries each
        $publishedDays = [];
        $scheduledDays = [];
        for ($i = 29; $i >= 0; $i--) {
            $publishedDays[$base->modify("-{$i} days")->format('Y-m-d')] = 0;
        }
        for ($i = 1; $i <= 30; $i++) {
            $scheduledDays[$base->modify("+{$i} days")->format('Y-m-d')] = 0;
        }
        $featuredDays          = $publishedDays; // same key range as published
        $featuredScheduledDays = $scheduledDays; // mirrors next-30-days window

        // Aggregate sparkline counts per published-day + featured flag.
        // DATE(published_date) collapses datetime to its calendar day; the
        // 30-day window is calendar-relative so this stays correct.
        $sql = <<<SQL
            SELECT
                DATE(published_date) AS published_day,
                featured,
                COUNT(*) AS cnt
            FROM insights
            WHERE tenant_id = ?
              AND (
                   (DATE(published_date) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE())
                OR (DATE(published_date) BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
              )
            GROUP BY DATE(published_date), featured
        SQL;
        $rows = $this->db->query(
            $sql,
            [$tenantId->value()],
        );

        foreach ($rows as $row) {
            $date     = self::str($row, 'published_day');
            $featured = self::intCol($row, 'featured') === 1;
            $cnt      = self::intCol($row, 'cnt');

            if ($date <= $today) {
                if (isset($publishedDays[$date])) {
                    $publishedDays[$date] += $cnt;
                }
                if ($featured && isset($featuredDays[$date])) {
                    $featuredDays[$date] += $cnt;
                }
            } else {
                if (isset($scheduledDays[$date])) {
                    $scheduledDays[$date] += $cnt;
                }
                if ($featured && isset($featuredScheduledDays[$date])) {
                    $featuredScheduledDays[$date] += $cnt;
                }
            }
        }

        // Accurate totals (full history, not limited to 30-day window).
        // NOW() keeps MySQL as the time authority for the published/scheduled
        // boundary — matters now that published_date is a DATETIME (an item
        // scheduled for 17:00 today should count as 'scheduled' all morning).
        // 'featured' counts ALL featured insights regardless of state (drafts,
        // scheduled, published) — the editor's intent to highlight is
        // independent of the publish lifecycle, and gating on NOW() made the
        // KPI read 0 even when a featured piece was queued for next month.
        $totals = $this->db->query(
            'SELECT
                SUM(CASE WHEN published_date <= NOW() THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN published_date >  NOW() THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) AS featured
             FROM insights
             WHERE tenant_id = ?',
            [$tenantId->value()],
        );
        $totalRow = $totals[0] ?? [];

        return [
            'published' => [
                'value'     => self::nullableIntCol($totalRow, 'published'),
                'sparkline' => self::seriesFromMap($publishedDays),
            ],
            'scheduled' => [
                'value'     => self::nullableIntCol($totalRow, 'scheduled'),
                'sparkline' => self::seriesFromMap($scheduledDays),
            ],
            'featured' => [
                'value'               => self::nullableIntCol($totalRow, 'featured'),
                'sparkline'           => self::seriesFromMap($featuredDays),
                'sparkline_scheduled' => self::seriesFromMap($featuredScheduledDays),
            ],
        ];
    }

    /**
     * @param array<string, int> $map
     * @return list<array{date: string, value: int}>
     */
    private static function seriesFromMap(array $map): array
    {
        $out = [];
        foreach ($map as $date => $value) {
            $out[] = ['date' => $date, 'value' => $value];
        }
        return $out;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Insight
    {
        $tagsRaw  = $row['tags_json'] ?? null;
        $tagsJson = is_string($tagsRaw) ? $tagsRaw : '[]';
        $tags     = json_decode($tagsJson, true);
        $tags     = is_array($tags) ? $tags : [];

        $insightId    = self::str($row, 'id');
        $translations = $this->loadTranslationMap($insightId);

        return new Insight(
            id: InsightId::fromString($insightId),
            tenantId: TenantId::fromString(self::str($row, 'tenant_id')),
            slug: self::str($row, 'slug'),
            title: self::firstAvailable($translations, 'title') ?? '',
            category: self::str($row, 'category'),
            categoryLabel: self::str($row, 'category_label'),
            featured: (bool) ($row['featured'] ?? false),
            date: self::strOrNull($row, 'published_date'),
            author: self::str($row, 'author'),
            readingTime: self::intCol($row, 'reading_time'),
            excerpt: self::firstAvailable($translations, 'excerpt') ?? '',
            heroImage: self::strOrNull($row, 'hero_image'),
            tags: $tags,
            content: self::firstAvailable($translations, 'content') ?? '',
            translations: $translations,
        );
    }

    /**
     * Build TranslationMap from insights_i18n rows. After A11/insights-009
     * insights_i18n is the sole source of truth — no legacy-column fallback.
     */
    private function loadTranslationMap(string $insightId): TranslationMap
    {
        $rows = $this->db->query(
            'SELECT locale, title, excerpt, content FROM insights_i18n WHERE insight_id = ?',
            [$insightId],
        );
        $map = [];
        foreach (SupportedLocale::supportedValues() as $loc) {
            $map[$loc] = null;
        }
        foreach ($rows as $r) {
            $loc = isset($r['locale']) && is_string($r['locale']) ? $r['locale'] : null;
            if ($loc === null || !SupportedLocale::isSupported($loc)) {
                continue;
            }
            $map[$loc] = [
                'title'   => isset($r['title'])   && is_string($r['title'])   ? $r['title']   : '',
                'excerpt' => isset($r['excerpt']) && is_string($r['excerpt']) ? $r['excerpt'] : '',
                'content' => isset($r['content']) && is_string($r['content']) ? $r['content'] : '',
            ];
        }
        return new TranslationMap($map);
    }

    private static function firstAvailable(TranslationMap $translations, string $field): ?string
    {
        foreach ([SupportedLocale::UI_DEFAULT, SupportedLocale::CONTENT_FALLBACK] as $loc) {
            $row = $translations->rowFor(SupportedLocale::fromString($loc));
            if ($row !== null && isset($row[$field]) && trim((string) $row[$field]) !== '') {
                return (string) $row[$field];
            }
        }
        foreach (SupportedLocale::supportedValues() as $loc) {
            $row = $translations->rowFor(SupportedLocale::fromString($loc));
            if ($row !== null && isset($row[$field]) && trim((string) $row[$field]) !== '') {
                return (string) $row[$field];
            }
        }
        return null;
    }

    /** @param array<string, ?string> $row */
    private function upsertTranslationRow(string $insightId, string $locale, array $row): void
    {
        $this->db->execute(
            'INSERT INTO insights_i18n (insight_id, locale, title, excerpt, content)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                title=VALUES(title), excerpt=VALUES(excerpt),
                content=VALUES(content), updated_at=CURRENT_TIMESTAMP',
            [
                $insightId,
                $locale,
                (string) ($row['title']   ?? ''),
                (string) ($row['excerpt'] ?? ''),
                (string) ($row['content'] ?? ''),
            ],
        );
    }

    /** @param array<string, mixed> $row */
    private static function str(array $row, string $key): string
    {
        $v = $row[$key] ?? null;
        if (is_string($v)) {
            return $v;
        }
        throw new \DomainException("Missing or non-string column: {$key}");
    }

    /** @param array<string, mixed> $row */
    private static function strOrNull(array $row, string $key): ?string
    {
        $v = $row[$key] ?? null;
        return is_string($v) ? $v : null;
    }

    /** @param array<string, mixed> $row */
    private static function intCol(array $row, string $key): int
    {
        $v = $row[$key] ?? null;
        if (is_int($v)) {
            return $v;
        }
        if (is_string($v) && is_numeric($v)) {
            return (int) $v;
        }
        throw new \DomainException("Missing or non-int column: {$key}");
    }

    /** @param array<string, mixed> $row */
    private static function nullableIntCol(array $row, string $key): int
    {
        $v = $row[$key] ?? null;
        if ($v === null) {
            return 0;
        }
        if (is_int($v)) {
            return $v;
        }
        if (is_string($v) && is_numeric($v)) {
            return (int) $v;
        }
        return 0;
    }
}
