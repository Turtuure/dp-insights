<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Tests\Support;

use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightId;
use DaemsModule\Insights\Domain\InsightRepositoryInterface;
use Daems\Domain\Locale\SupportedLocale;
use Daems\Domain\Locale\TranslationMap;
use Daems\Domain\Tenant\TenantId;

final class InMemoryInsightRepository implements InsightRepositoryInterface
{
    /** @var array<string, Insight> */
    public array $bySlug = [];

    /**
     * @var array<string, array<string, array<string, string>>>
     *      insightId => locale => [title, excerpt, content]
     *
     * Mirrors the SQL repo's insights_i18n table so unit/E2E callers get
     * a faithful view from saveTranslation() + the rebuild-on-read path.
     */
    public array $translations = [];

    public function listForTenant(TenantId $tenantId, ?string $category = null, bool $includeUnpublished = false): array
    {
        return array_values(array_filter(
            array_map(fn(Insight $i): Insight => $this->withTranslations($i), $this->bySlug),
            static fn(Insight $i): bool => $i->tenantId()->equals($tenantId)
                && ($category === null || $i->category() === $category),
        ));
    }

    public function findBySlugForTenant(string $slug, TenantId $tenantId): ?Insight
    {
        $insight = $this->bySlug[$slug] ?? null;
        if ($insight === null) {
            return null;
        }
        return $insight->tenantId()->equals($tenantId) ? $this->withTranslations($insight) : null;
    }

    public function findByIdForTenant(InsightId $id, TenantId $tenantId): ?Insight
    {
        foreach ($this->bySlug as $insight) {
            if ($insight->id()->value() === $id->value() && $insight->tenantId()->equals($tenantId)) {
                return $this->withTranslations($insight);
            }
        }
        return null;
    }

    public function save(Insight $insight): void
    {
        $this->bySlug[$insight->slug()] = $insight;
        // Persist whatever locales the entity carried (mirroring the SQL
        // repo: missing locales stay untouched, present ones are upserted).
        foreach ($insight->translations()->raw() as $locale => $row) {
            if ($row === null || !SupportedLocale::isSupported($locale)) {
                continue;
            }
            $this->translations[$insight->id()->value()][$locale] = [
                'title'   => (string) ($row['title']   ?? ''),
                'excerpt' => (string) ($row['excerpt'] ?? ''),
                'content' => (string) ($row['content'] ?? ''),
            ];
        }
    }

    public function delete(InsightId $id, TenantId $tenantId): void
    {
        foreach ($this->bySlug as $slug => $insight) {
            if ($insight->id()->value() === $id->value() && $insight->tenantId()->equals($tenantId)) {
                unset($this->bySlug[$slug], $this->translations[$id->value()]);
                return;
            }
        }
    }

    public function saveTranslation(
        TenantId $tenantId,
        string $insightId,
        SupportedLocale $locale,
        array $fields,
    ): void {
        $owner = null;
        foreach ($this->bySlug as $insight) {
            if ($insight->id()->value() === $insightId && $insight->tenantId()->equals($tenantId)) {
                $owner = $insight;
                break;
            }
        }
        if ($owner === null) {
            throw new \DomainException('insight_not_found_in_tenant');
        }
        $this->translations[$insightId][$locale->value()] = [
            'title'   => (string) ($fields['title']   ?? ''),
            'excerpt' => (string) ($fields['excerpt'] ?? ''),
            'content' => (string) ($fields['content'] ?? ''),
        ];
    }

    public function statsForTenant(TenantId $tenantId): array
    {
        $base  = new \DateTimeImmutable('today');
        $today = $base->format('Y-m-d');

        $publishedDays         = [];
        $featuredDays          = [];
        $scheduledDays         = [];
        $featuredScheduledDays = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = $base->modify("-{$i} days")->format('Y-m-d');
            $publishedDays[$d] = 0;
            $featuredDays[$d]  = 0;
        }
        for ($i = 1; $i <= 30; $i++) {
            $d = $base->modify("+{$i} days")->format('Y-m-d');
            $scheduledDays[$d]         = 0;
            $featuredScheduledDays[$d] = 0;
        }

        $publishedTotal = 0;
        $scheduledTotal = 0;
        $featuredTotal  = 0;

        foreach ($this->bySlug as $insight) {
            if ($insight->tenantId()->value() !== $tenantId->value()) {
                continue;
            }
            $date = $insight->date();
            if ($date <= $today) {
                $publishedTotal++;
                if (isset($publishedDays[$date])) {
                    $publishedDays[$date]++;
                }
                if ($insight->featured()) {
                    $featuredTotal++;
                    if (isset($featuredDays[$date])) {
                        $featuredDays[$date]++;
                    }
                }
            } else {
                $scheduledTotal++;
                if (isset($scheduledDays[$date])) {
                    $scheduledDays[$date]++;
                }
                if ($insight->featured() && isset($featuredScheduledDays[$date])) {
                    $featuredScheduledDays[$date]++;
                }
            }
        }

        return [
            'published' => ['value' => $publishedTotal, 'sparkline' => self::seriesFromMap($publishedDays)],
            'scheduled' => ['value' => $scheduledTotal, 'sparkline' => self::seriesFromMap($scheduledDays)],
            'featured'  => [
                'value'               => $featuredTotal,
                'sparkline'           => self::seriesFromMap($featuredDays),
                'sparkline_scheduled' => self::seriesFromMap($featuredScheduledDays),
            ],
        ];
    }

    /**
     * Re-hydrate an Insight with its currently-stored translations map so
     * tests that mutate translations via saveTranslation() see the change
     * via subsequent find* calls.
     */
    private function withTranslations(Insight $insight): Insight
    {
        $stored = $this->translations[$insight->id()->value()] ?? [];
        if ($stored === []) {
            return $insight;
        }
        $map = [];
        foreach (SupportedLocale::supportedValues() as $loc) {
            $map[$loc] = $stored[$loc] ?? null;
        }
        return new Insight(
            id: $insight->id(),
            tenantId: $insight->tenantId(),
            slug: $insight->slug(),
            title: $insight->title(),
            category: $insight->category(),
            categoryLabel: $insight->categoryLabel(),
            featured: $insight->featured(),
            date: $insight->date(),
            author: $insight->author(),
            readingTime: $insight->readingTime(),
            excerpt: $insight->excerpt(),
            heroImage: $insight->heroImage(),
            tags: $insight->tags(),
            content: $insight->content(),
            translations: new TranslationMap($map),
        );
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
}
