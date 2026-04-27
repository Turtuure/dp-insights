<?php
declare(strict_types=1);

namespace DaemsModule\Insights\Tests\Integration;

use DaemsModule\Insights\Domain\InsightId;
use DaemsModule\Insights\Infrastructure\SqlInsightRepository;
use Daems\Domain\Shared\ValueObject\Uuid7;
use Daems\Domain\Tenant\TenantId;
use Daems\Infrastructure\Framework\Database\Connection;
use Daems\Tests\Integration\MigrationTestCase;

final class SqlInsightRepositoryTest extends MigrationTestCase
{
    private Connection $conn;
    private TenantId $tenantA;
    private TenantId $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrationsUpTo(61);

        $this->conn = new Connection([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'daems_db_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASS') ?: 'salasana',
        ]);

        $this->tenantA = TenantId::fromString(Uuid7::generate()->value());
        $this->tenantB = TenantId::fromString(Uuid7::generate()->value());
        $this->pdo()->prepare('INSERT INTO tenants (id, slug, name, created_at) VALUES (?,?,?,NOW())')
            ->execute([$this->tenantA->value(), 'daems-sir', 'Daems SIR']);
        $this->pdo()->prepare('INSERT INTO tenants (id, slug, name, created_at) VALUES (?,?,?,NOW())')
            ->execute([$this->tenantB->value(), 'sahegroup-sir', 'SaheGroup SIR']);
    }

    public function test_delete_only_within_tenant(): void
    {
        $repo = new SqlInsightRepository($this->conn);
        $id = InsightId::fromString(Uuid7::generate()->value());

        $this->seedInsight($id->value(), $this->tenantA->value(), 'del-test-' . substr($id->value(), -8), 'Delete me');

        // Wrong tenant — row survives
        $repo->delete($id, $this->tenantB);
        $count = (int) $this->pdo()->query("SELECT COUNT(*) FROM insights WHERE id = '{$id->value()}'")->fetchColumn();
        self::assertSame(1, $count);

        // Correct tenant — row gone (CASCADE removes insights_i18n).
        $repo->delete($id, $this->tenantA);
        $count = (int) $this->pdo()->query("SELECT COUNT(*) FROM insights WHERE id = '{$id->value()}'")->fetchColumn();
        self::assertSame(0, $count);
        $i18n = (int) $this->pdo()->query("SELECT COUNT(*) FROM insights_i18n WHERE insight_id = '{$id->value()}'")->fetchColumn();
        self::assertSame(0, $i18n);
    }

    public function test_list_for_tenant_filters_future_dated_when_include_unpublished_false(): void
    {
        $repo = new SqlInsightRepository($this->conn);
        $pastId   = InsightId::fromString(Uuid7::generate()->value());
        $futureId = InsightId::fromString(Uuid7::generate()->value());

        $this->seedInsight(
            $pastId->value(), $this->tenantA->value(),
            'past-' . substr($pastId->value(), -6), 'Past',
            publishedDate: '2026-01-01',
        );
        $this->seedInsight(
            $futureId->value(), $this->tenantA->value(),
            'future-' . substr($futureId->value(), -6), 'Future',
            publishedDate: '2099-01-01',
        );

        $public = $repo->listForTenant($this->tenantA, null, false);
        $titles = array_map(fn($i) => $i->title(), $public);
        self::assertContains('Past', $titles);
        self::assertNotContains('Future', $titles);

        $admin = $repo->listForTenant($this->tenantA, null, true);
        $adminTitles = array_map(fn($i) => $i->title(), $admin);
        self::assertContains('Future', $adminTitles);
    }

    /**
     * Helper that mirrors the post-i18n shape: insert chrome row then a
     * fi_FI translation row for a given title.
     */
    private function seedInsight(
        string $id,
        string $tenantId,
        string $slug,
        string $title,
        string $excerpt = 'x',
        string $content = 'body',
        string $publishedDate = '2026-01-01',
    ): void {
        $this->pdo()->prepare(
            "INSERT INTO insights (id, tenant_id, slug, category, category_label, featured,
                                   published_date, author, reading_time, tags_json, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
        )->execute([
            $id, $tenantId, $slug, 'tech', 'Tech', 0, $publishedDate, 'Sam', 1, '[]',
        ]);
        $this->pdo()->prepare(
            'INSERT INTO insights_i18n (insight_id, locale, title, excerpt, content)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$id, 'fi_FI', $title, $excerpt, $content]);
    }
}
