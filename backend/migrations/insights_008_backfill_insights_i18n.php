<?php
// insights_008_backfill_insights_i18n.php
// One-off backfill — copies existing insights.title/excerpt/content rows
// into insights_i18n with locale='fi_FI' (UI default). Idempotent via
// ON DUPLICATE KEY UPDATE. Mirrors core 053_backfill_events_projects_i18n
// pattern but is module-local because the table lives in the module.
declare(strict_types=1);

/** @var \PDO $pdo MigrationTestCase passes its own; standalone falls back. */
if (!isset($pdo) || !$pdo instanceof PDO) {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=daems_db;charset=utf8mb4', 'root', 'salasana', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

$rows = $pdo->query('SELECT id, title, excerpt, content FROM insights')->fetchAll(\PDO::FETCH_ASSOC);
$stmt = $pdo->prepare(
    'INSERT INTO insights_i18n (insight_id, locale, title, excerpt, content)
     VALUES (:id, :locale, :title, :excerpt, :content)
     ON DUPLICATE KEY UPDATE
        title   = VALUES(title),
        excerpt = VALUES(excerpt),
        content = VALUES(content),
        updated_at = CURRENT_TIMESTAMP'
);

$copied = 0;
foreach ($rows as $row) {
    $stmt->execute([
        'id'      => (string) $row['id'],
        'locale'  => 'fi_FI',
        'title'   => (string) ($row['title']   ?? ''),
        'excerpt' => (string) ($row['excerpt'] ?? ''),
        'content' => (string) ($row['content'] ?? ''),
    ]);
    $copied++;
}

if (php_sapi_name() === 'cli') {
    echo "Backfilled {$copied} insight row(s) to insights_i18n[fi_FI]\n";
}
