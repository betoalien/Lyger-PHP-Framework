<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Lyger\Database\QueryBuilder;

$db = __DIR__ . '/../database/database.sqlite';
@unlink($db);
$pdo = new PDO("sqlite:{$db}");
$pdo->exec('CREATE TABLE contract_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, score INTEGER NOT NULL)');

$builder = QueryBuilder::table('contract_items');
if (!$builder->insert(['name' => 'one', 'score' => 1])) {
    throw new RuntimeException('insert failed');
}
$id = QueryBuilder::table('contract_items')->insertGetId(['name' => 'two', 'score' => 2]);
if ((int) $id !== 2) {
    throw new RuntimeException("insertGetId failed: {$id}");
}
$updated = QueryBuilder::table('contract_items')->where('name', 'two')->update(['score' => 20]);
if ($updated !== 1) {
    throw new RuntimeException('update binding order failed');
}
$row = QueryBuilder::table('contract_items')->where('score', 20)->first();
if ($row === null || $row['name'] !== 'two') {
    throw new RuntimeException('select contract failed');
}
echo "PHP database contract: PASS\n";
