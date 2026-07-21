<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use Lyger\Database\RustDatabaseDriver;

$dsn = $argv[1] ?? 'sqlite:file:php_driver_contract?mode=memory&cache=shared';
$driver = new RustDatabaseDriver($dsn);
$driver->query('CREATE TABLE IF NOT EXISTS driver_items (id INTEGER PRIMARY KEY, name TEXT)', []);
$driver->query('DELETE FROM driver_items', []);
$driver->query('INSERT INTO driver_items (id, name) VALUES (?, ?)', [1, 'rust']);
$rows = $driver->query('SELECT name FROM driver_items WHERE id = ?', [1]);
if (($rows['rows'][0]['name'] ?? null) !== 'rust') {
    throw new RuntimeException('Rust driver returned unexpected rows');
}
echo "Rust PHP driver ({$driver->name()}): PASS {$dsn}\n";
