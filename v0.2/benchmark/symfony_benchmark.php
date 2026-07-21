<?php

/**
 * Symfony Benchmark - Same tests as Lyger but using Symfony-style operations
 */

echo "
========================================
   SYMFONY FRAMEWORK BENCHMARK
========================================

Note: This benchmark simulates Symfony's performance
      using similar operations without requiring full installation
";

$startTime = microtime(true);

// Test 1: Hello World
echo "\n📊 Test 1: Hello World (1000 iterations)\n";
echo "----------------------------------------\n";

$iterations = 1000;
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $response = "Hello World";
}
$end = microtime(true);
$totalTime = ($end - $start) * 1000;
$symfony['hello_world'] = round($totalTime, 2);

echo "   Total: {$symfony['hello_world']}ms\n";
echo "   Average: " . round($totalTime / $iterations, 4) . "ms\n";
echo "   Throughput: " . round($iterations / ($end - $start)) . " req/s\n\n";

// Test 2: Heavy Computation (no Rust FFI)
echo "📊 Test 2: Heavy Computation (10M iterations)\n";
echo "----------------------------------------\n";

$iterations = 10000000;
$start = microtime(true);
$result = 0;
for ($i = 0; $i < $iterations; $i++) {
    $result += sqrt($i) * sin($i);
}
$phpTime = (microtime(true) - $start) * 1000;

$symfony['heavy_computation'] = [
    'php_ms' => round($phpTime, 0),
    'rust_ms' => 'N/A',
    'speedup' => 'baseline'
];

echo "   PHP (Symfony): {$symfony['heavy_computation']['php_ms']}ms\n";
echo "   Note: Symfony doesn't have Rust FFI\n\n";

// Test 3: JSON Serialization
echo "📊 Test 3: JSON Serialization (1000 objects)\n";
echo "----------------------------------------\n";

$data = [];
for ($i = 0; $i < 1000; $i++) {
    $data[] = [
        'id' => $i,
        'name' => 'Product ' . $i,
        'price' => rand(100, 10000) / 100,
        'description' => 'Description ' . $i,
    ];
}

$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $json = json_encode($data);
}
$phpTime = (microtime(true) - $start) * 1000;

$symfony['json_serialization'] = round($phpTime, 2);
echo "   PHP: {$symfony['json_serialization']}ms\n\n";

// Test 4: String Operations
echo "📊 Test 4: String Operations (1M operations)\n";
echo "----------------------------------------\n";

$text = "The quick brown fox jumps over the lazy dog.";

$start = microtime(true);
for ($i = 0; $i < 1000000; $i++) {
    $result = strtoupper($text);
    $result = strtolower($text);
    $result = substr($text, 0, 50);
}
$phpTime = (microtime(true) - $start) * 1000;

$symfony['string_operations'] = round($phpTime, 0);
echo "   PHP: {$symfony['string_operations']}ms\n\n";

// Test 5: Memory Usage (Symfony is heavier)
echo "📊 Test 5: Memory Usage\n";
echo "----------------------------------------\n";

gc_collect_cycles();
$before = memory_get_usage(true);

$data = [];
for ($i = 0; $i < 10000; $i++) {
    $data[] = ['id' => $i, 'data' => str_repeat('x', 1000)];
}

$after = memory_get_usage(true);
$peak = memory_get_peak_usage(true);

$symfony['memory'] = [
    'used_mb' => round(($after - $before) / 1024 / 1024, 2),
    'peak_mb' => round($peak / 1024 / 1024, 2)
];

echo "   Used: {$symfony['memory']['used_mb']} MB\n";
echo "   Peak: {$symfony['memory']['peak_mb']} MB\n\n";

// Test 6: Database CRUD
echo "📊 Test 6: Database CRUD\n";
echo "----------------------------------------\n";

try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/../database/benchmark.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("DROP TABLE IF EXISTS benchmark");
    $pdo->exec("CREATE TABLE benchmark (id INTEGER PRIMARY KEY, data TEXT, value INTEGER)");

    // Insert
    $start = microtime(true);
    $stmt = $pdo->prepare("INSERT INTO benchmark (data, value) VALUES (?, ?)");
    for ($i = 0; $i < 1000; $i++) {
        $stmt->execute(['Test data ' . $i, $i]);
    }
    $insertTime = (microtime(true) - $start) * 1000;

    // Select
    $start = microtime(true);
    $pdo->query("SELECT * FROM benchmark")->fetchAll(PDO::FETCH_ASSOC);
    $selectTime = (microtime(true) - $start) * 1000;

    // Update
    $start = microtime(true);
    $pdo->exec("UPDATE benchmark SET value = value * 2");
    $updateTime = (microtime(true) - $start) * 1000;

    // Delete
    $start = microtime(true);
    $pdo->exec("DELETE FROM benchmark");
    $deleteTime = (microtime(true) - $start) * 1000;

    $symfony['database_crud'] = [
        'insert_ms' => round($insertTime, 2),
        'select_ms' => round($selectTime, 2),
        'update_ms' => round($updateTime, 2),
        'delete_ms' => round($deleteTime, 2),
        'total_ms' => round($insertTime + $selectTime + $updateTime + $deleteTime, 2),
    ];

    echo "   Insert: {$symfony['database_crud']['insert_ms']}ms\n";
    echo "   Select: {$symfony['database_crud']['select_ms']}ms\n";
    echo "   Update: {$symfony['database_crud']['update_ms']}ms\n";
    echo "   Delete: {$symfony['database_crud']['delete_ms']}ms\n";
    echo "   Total: {$symfony['database_crud']['total_ms']}ms\n\n";
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Summary
$totalTime = (microtime(true) - $startTime) * 1000;

echo "========================================\n";
echo "        SYMFONY RESULTS\n";
echo "========================================\n\n";

echo "Test                   Time (ms)\n";
echo "----                   ---------\n";
echo "Hello World:          {$symfony['hello_world']}\n";
echo "Heavy Computation:   {$symfony['heavy_computation']['php_ms']}\n";
echo "JSON Serialization:  {$symfony['json_serialization']}\n";
echo "String Operations:   {$symfony['string_operations']}\n";
echo "Memory Used:          {$symfony['memory']['used_mb']} MB\n";
echo "Database CRUD:        {$symfony['database_crud']['total_ms']}\n\n";

echo "Total benchmark time: " . round($totalTime, 0) . "ms\n";
echo "========================================\n\n";
