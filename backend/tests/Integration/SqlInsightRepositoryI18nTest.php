<?php

declare(strict_types=1);

namespace DaemsModule\Insights\Tests\Integration;

use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightId;
use DaemsModule\Insights\Infrastructure\SqlInsightRepository;
use Daems\Domain\Locale\SupportedLocale;
use Daems\Domain\Locale\TranslationMap;
use Daems\Domain\Shared\ValueObject\Uuid7;
use Daems\Domain\Tenant\TenantId;
use Daems\Infrastructure\Framework\Database\Connection;
use Daems\Tests\Integration\MigrationTestCase;

final class SqlInsightRepositoryI18nTest extends MigrationTestCase
{
    private Connection $conn;
    private TenantId $tenantA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrationsUpTo(61);

        $this->conn = new Connection([
            'host'     => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port'     => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'daems_db_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASS') ?: 'salasana',
        ]);

        $this->tenantA = TenantId::fromString(Uuid7::generate()->value());
        $this->pdo()->prepare('INSERT INTO tenants (id, slug, name, created_at) VALUES (?,?,?,NOW())')
            ->execute([$this->tenantA->value(), 'daems-i18n', 'Daems i18n']);
    }

    public function test_save_persists_seed_fi_FI_translation_row(): void
    {
        $repo  = new SqlInsightRepository($this->conn);
        $id    = InsightId::fromString(Uuid7::generate()->value());
        $insight = $this->buildInsight($id, slug: 'seed-test', title: 'Hei', excerpt: 'esittely', content: '<p>runko</p>');
        $repo->save($insight);

        $row = $this->pdo()->prepare(
            'SELECT title, excerpt, content FROM insights_i18n WHERE insight_id = ? AND locale = ?'
        );
        $row->execute([$id->value(), 'fi_FI']);
        $r = $row->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('Hei',          $r['title']);
        self::assertSame('esittely',     $r['excerpt']);
        self::assertSame('<p>runko</p>', $r['content']);
    }

    public function test_findByIdForTenant_returns_translation_map(): void
    {
        $repo  = new SqlInsightRepository($this->conn);
        $id    = InsightId::fromString(Uuid7::generate()->value());
        $repo->save($this->buildInsight($id, slug: 'map-test', title: 'Hei'));

        // Add an en_GB row directly.
        $this->pdo()->prepare(
            'INSERT INTO insights_i18n (insight_id, locale, title, excerpt, content) VALUES (?,?,?,?,?)'
        )->execute([$id->value(), 'en_GB', 'Hello', 'lead', '<p>body</p>']);

        $found = $repo->findByIdForTenant($id, $this->tenantA);
        self::assertNotNull($found);
        $rowFi = $found->translations()->rowFor(SupportedLocale::uiDefault());
        $rowEn = $found->translations()->rowFor(SupportedLocale::contentFallback());
        self::assertNotNull($rowFi);
        self::assertNotNull($rowEn);
        self::assertSame('Hei',   $rowFi['title']);
        self::assertSame('Hello', $rowEn['title']);
        // The convenience scalar prefers fi_FI.
        self::assertSame('Hei',   $found->title());
    }

    public function test_saveTranslation_upserts_row_for_locale(): void
    {
        $repo = new SqlInsightRepository($this->conn);
        $id   = InsightId::fromString(Uuid7::generate()->value());
        $repo->save($this->buildInsight($id, slug: 'st-test', title: 'Hei'));

        $repo->saveTranslation(
            $this->tenantA,
            $id->value(),
            SupportedLocale::contentFallback(),
            ['title' => 'Hello', 'excerpt' => 'lead', 'content' => '<p>body</p>'],
        );

        $rows = $this->pdo()->prepare(
            'SELECT locale, title FROM insights_i18n WHERE insight_id = ? ORDER BY locale'
        );
        $rows->execute([$id->value()]);
        $all = $rows->fetchAll(\PDO::FETCH_KEY_PAIR);
        self::assertSame('Hello', $all['en_GB']);
        self::assertSame('Hei',   $all['fi_FI']);
    }

    public function test_saveTranslation_for_fi_FI_refreshes_search_text(): void
    {
        $repo = new SqlInsightRepository($this->conn);
        $id   = InsightId::fromString(Uuid7::generate()->value());
        $repo->save($this->buildInsight($id, slug: 'st-search', title: 'Hei'));

        $repo->saveTranslation(
            $this->tenantA,
            $id->value(),
            SupportedLocale::uiDefault(),
            ['title' => 'Hei', 'excerpt' => 'esittely', 'content' => '<p>painava <strong>uusi</strong> runko</p>'],
        );

        $st = (string) $this->pdo()->query("SELECT search_text FROM insights WHERE id = '{$id->value()}'")
            ->fetchColumn();
        self::assertStringContainsString('painava',     $st);
        self::assertStringContainsString('uusi',        $st);
        self::assertStringNotContainsString('<strong>', $st);
    }

    public function test_saveTranslation_throws_for_unknown_insight(): void
    {
        $repo = new SqlInsightRepository($this->conn);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('insight_not_found_in_tenant');
        $repo->saveTranslation(
            $this->tenantA,
            Uuid7::generate()->value(),
            SupportedLocale::uiDefault(),
            ['title' => 'X', 'excerpt' => 'Y', 'content' => 'Z'],
        );
    }

    public function test_delete_cascades_translations(): void
    {
        $repo = new SqlInsightRepository($this->conn);
        $id   = InsightId::fromString(Uuid7::generate()->value());
        $repo->save($this->buildInsight($id, slug: 'cascade-test', title: 'Hei'));
        $repo->saveTranslation(
            $this->tenantA, $id->value(), SupportedLocale::contentFallback(),
            ['title' => 'Hello', 'excerpt' => 'lead', 'content' => 'body'],
        );

        $repo->delete($id, $this->tenantA);

        $count = (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM insights_i18n WHERE insight_id = '{$id->value()}'"
        )->fetchColumn();
        self::assertSame(0, $count);
    }

    private function buildInsight(
        InsightId $id,
        string $slug,
        string $title,
        string $excerpt = 'x',
        string $content = '<p>body</p>',
    ): Insight {
        return new Insight(
            id: $id,
            tenantId: $this->tenantA,
            slug: $slug,
            title: $title,
            category: 'tech',
            categoryLabel: 'Tech',
            featured: false,
            date: '2026-04-24',
            author: 'Sam',
            readingTime: 1,
            excerpt: $excerpt,
            heroImage: null,
            tags: [],
            content: $content,
            translations: new TranslationMap([
                'fi_FI' => ['title' => $title, 'excerpt' => $excerpt, 'content' => $content],
                'en_GB' => null,
                'sw_TZ' => null,
            ]),
        );
    }
}
