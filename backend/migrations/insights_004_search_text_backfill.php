<?php
// One-off backfill — strips HTML from insights.content into insights.search_text.
// Runtime writes go through SqlInsightRepository::save() instead.
//
// 2026-04-28: Made harness-friendly so the integration test runner (which
// passes its own $pdo) doesn't try to connect to the dev DB. Falls back to
// daems_db when run standalone via CLI.
declare(strict_types=1);

/** @var \PDO $pdo MigrationTestCase passes its own; standalone falls back. */
if (!isset($pdo) || !$pdo instanceof PDO) {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=daems_db;charset=utf8mb4', 'root', 'salasana', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

$rows = $pdo->query('SELECT id, content FROM insights WHERE search_text IS NULL')->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare('UPDATE insights SET search_text = ? WHERE id = ?');
foreach ($rows as $r) {
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $r['content'])) ?? '');
    $stmt->execute([$plain, $r['id']]);
}
if (php_sapi_name() === 'cli') {
    echo "Backfilled " . count($rows) . " rows\n";
}
