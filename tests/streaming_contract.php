<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$engine = Lyger\Core\Engine::getInstance();
$dsn = 'sqlite:/tmp/lyger_v02_streaming_contract.sqlite';
@unlink('/tmp/lyger_v02_streaming_contract.sqlite');
$engine->dbQuery($dsn, 'CREATE TABLE stream_items (id INTEGER, name TEXT)');
$engine->dbQuery($dsn, "INSERT INTO stream_items VALUES (1, 'one'), (2, 'two'), (3, 'three')");
$handle = $engine->dbQuery($dsn, 'SELECT id, name FROM stream_items ORDER BY id');
if ($handle === 0) {
    throw new RuntimeException('Could not create streaming result');
}
try {
    $rows = iterator_to_array($engine->streamResult($handle, 2), false);
    if (count($rows) !== 3 || $rows[2]['name'] !== 'three') {
        throw new RuntimeException('Streaming order or count failed');
    }
    $metrics = $engine->memoryMetrics();
    if (!isset($metrics['used_bytes'], $metrics['budget_bytes'])) {
        throw new RuntimeException('Memory metrics contract failed');
    }
} finally {
    $engine->freeResult($handle);
}
echo "v0.2 streaming contract: PASS\n";
