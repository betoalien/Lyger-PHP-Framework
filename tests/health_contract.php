<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
if (!Lyger\Database\DatabaseManager::health('sqlite:file=health_contract?mode=memory&cache=shared')) {
    throw new RuntimeException('Rust health check failed');
}
if (!Lyger\Database\DatabaseManager::healthWithRetry('sqlite:file=health_contract?mode=memory&cache=shared', 3, 1)) {
    throw new RuntimeException('Rust health retry failed');
}
echo "v0.2 health: PASS\n";
