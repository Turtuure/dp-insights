<?php
declare(strict_types=1);

namespace DaemsModule\Insights\Tests\Integration;

use Daems\Domain\Shared\ValueObject\Uuid7;
use Daems\Domain\Tenant\TenantId;
use Daems\Infrastructure\Framework\Database\Connection;
use Daems\Tests\Integration\MigrationTestCase;
use DaemsModule\Insights\Domain\Insight;
use DaemsModule\Insights\Domain\InsightId;
use DaemsModule\Insights\Infrastructure\SqlInsightRepository;

final class InsightSearchSyncTest extends MigrationTestCase
{
    private Connection $conn;
    private string $tenantId;

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
        $this->tenantId = Uuid7::generate()->value();
        $this->pdo()->prepare('INSERT INTO tenants (id, slug, name, created_at) VALUES (?,?,?,NOW())')
            ->execute([$this->tenantId, 'daems-sync', 'Daems Sync']);
    }

    public function test_insight_save_populates_search_text_from_stripped_content(): void
    {
        $repo = new SqlInsightRepository($this->conn);
        $insightId = InsightId::generate();
        $id = $insightId->value();
        $insight = new Insight(
            id: $insightId,
            tenantId: TenantId::fromString($this->tenantId),
            slug: 'sync-check-' . substr($id, 0, 8),
            title: 'Sync Check',
            category: 'tech',
            categoryLabel: 'Tech',
            featured: false,
            date: '2026-04-24',
            author: 'Sam',
            readingTime: 3,
            excerpt: 'x',
            heroImage: null,
            tags: [],
            content: '<p>plain text here and <strong>bold bits</strong></p>',
        );
        $repo->save($insight);

        $row = $this->pdo()->query("SELECT search_text FROM insights WHERE id = '{$id}'")
            ->fetch(\PDO::FETCH_ASSOC);
        self::assertStringContainsString('plain text here', (string) $row['search_text']);
        self::assertStringNotContainsString('<strong>', (string) $row['search_text']);
    }
}
