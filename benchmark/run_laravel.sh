#!/bin/bash

# Install Laravel and run benchmarks

echo "========================================="
echo "  LARAVEL BENCHMARK INSTALLATION"
echo "========================================="

# Check if Laravel is already installed
if [ -d "laravel_test" ]; then
    echo "Laravel already installed, skipping..."
else
    echo "Installing Laravel..."
    composer create-project laravel/laravel laravel_test --prefer-dist --no-interaction 2>&1 | tail -20
    echo "Laravel installed!"
fi

cd laravel_test

# Create benchmark file
cat > benchmark.php << 'BENCHMARK'
<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class LaravelBenchmark
{
    private array $results = [];

    public function run(): void
    {
        echo "\n========================================\n";
        echo "   LARAVEL FRAMEWORK BENCHMARK\n";
        echo "========================================\n\n";

        $this->testHelloWorld();
        $this->testHeavyComputation();
        $this->testJsonSerialization();
        $this->testStringOperations();
        $this->testMemoryUsage();

        // Test database if available
        try {
            \DB::connection()->getPdo();
            $this->testDatabaseCRUD();
        } catch (\Exception $e) {
            echo "⚠️  Database tests skipped\n";
        }

        $this->printResults();
    }

    private function testHelloWorld(): void
    {
        echo "📊 Test 1: Hello World (1000 iterations)\n";
        echo "----------------------------------------\n";

        $iterations = 1000;
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $response = "Hello World";
        }
        $end = microtime(true);
        $totalTime = ($end - $start) * 1000;
        $avgTime = $totalTime / $iterations;
        $rps = $iterations / ($end - $start);

        $this->results['hello_world'] = [
            'total_ms' => round($totalTime, 2),
            'avg_ms' => round($avgTime, 4),
            'rps' => round($rps, 0),
        ];

        echo "   Total: {$this->results['hello_world']['total_ms']}ms\n";
        echo "   Average: {$this->results['hello_world']['avg_ms']}ms\n";
        echo "   Throughput: {$this->results['hello_world']['rps']} req/s\n\n";
    }

    private function testHeavyComputation(): void
    {
        echo "📊 Test 2: Heavy Computation (10M iterations)\n";
        echo "----------------------------------------\n";

        $start = microtime(true);
        $result = 0;
        for ($i = 0; $i < 10000000; $i++) {
            $result += sqrt($i) * sin($i);
        }
        $phpTime = (microtime(true) - $start) * 1000;

        $this->results['heavy_computation'] = [
            'php_ms' => round($phpTime, 0),
        ];

        echo "   PHP: {$this->results['heavy_computation']['php_ms']}ms\n";
        echo "   Note: Laravel doesn't have Rust FFI\n\n";
    }

    private function testJsonSerialization(): void
    {
        echo "📊 Test 3: JSON Serialization (1000 objects)\n";
        echo "----------------------------------------\n";

        $data = [];
        for ($i = 0; $i < 1000; $i++) {
            $data[] = [
                'id' => $i,
                'name' => 'Product ' . $i,
                'price' => rand(100, 10000) / 100,
                'description' => 'Description ' . $i,
                'category' => ['Electronics', 'Clothing', 'Food'][rand(0, 2)],
            ];
        }

        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $json = json_encode($data);
        }
        $phpTime = (microtime(true) - $start) * 1000;

        $this->results['json_serialization'] = [
            'php_ms' => round($phpTime, 2),
        ];

        echo "   PHP: {$this->results['json_serialization']['php_ms']}ms\n\n";
    }

    private function testStringOperations(): void
    {
        echo "📊 Test 4: String Operations (1M operations)\n";
        echo "----------------------------------------\n";

        $text = "The quick brown fox jumps over the lazy dog.";

        $start = microtime(true);
        for ($i = 0; $i < 1000000; $i++) {
            $result = strtoupper($text);
            $result = strtolower($text);
        }
        $phpTime = (microtime(true) - $start) * 1000;

        $this->results['string_operations'] = [
            'php_ms' => round($phpTime, 0),
        ];

        echo "   PHP: {$this->results['string_operations']['php_ms']}ms\n\n";
    }

    private function testMemoryUsage(): void
    {
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

        $this->results['memory'] = [
            'used_mb' => round(($after - $before) / 1024 / 1024, 2),
            'peak_mb' => round($peak / 1024 / 1024, 2),
        ];

        echo "   Used: {$this->results['memory']['used_mb']} MB\n";
        echo "   Peak: {$this->results['memory']['peak_mb']} MB\n\n";
    }

    private function testDatabaseCRUD(): void
    {
        echo "📊 Test 6: Database CRUD\n";
        echo "----------------------------------------\n";

        try {
            \DB::statement("DROP TABLE IF EXISTS benchmark");
            \DB::statement("CREATE TABLE benchmark (id INTEGER PRIMARY KEY AUTOINCREMENT, data TEXT, value INTEGER)");

            // Insert
            $start = microtime(true);
            for ($i = 0; $i < 1000; $i++) {
                \DB::table('benchmark')->insert(['data' => 'Test data ' . $i, 'value' => $i]);
            }
            $insertTime = (microtime(true) - $start) * 1000;

            // Select
            $start = microtime(true);
            \DB::table('benchmark')->get();
            $selectTime = (microtime(true) - $start) * 1000;

            // Update
            $start = microtime(true);
            \DB::table('benchmark')->update(['value' => \DB::raw('value * 2')]);
            $updateTime = (microtime(true) - $start) * 1000;

            // Delete
            $start = microtime(true);
            \DB::table('benchmark')->delete();
            $deleteTime = (microtime(true) - $start) * 1000;

            $this->results['database_crud'] = [
                'insert_ms' => round($insertTime, 2),
                'select_ms' => round($selectTime, 2),
                'update_ms' => round($updateTime, 2),
                'delete_ms' => round($deleteTime, 2),
                'total_ms' => round($insertTime + $selectTime + $updateTime + $deleteTime, 2),
            ];

            echo "   Insert: {$this->results['database_crud']['insert_ms']}ms\n";
            echo "   Select: {$this->results['database_crud']['select_ms']}ms\n";
            echo "   Update: {$this->results['database_crud']['update_ms']}ms\n";
            echo "   Delete: {$this->results['database_crud']['delete_ms']}ms\n";
            echo "   Total: {$this->results['database_crud']['total_ms']}ms\n\n";
        } catch (\Exception $e) {
            echo "   Error: {$e->getMessage()}\n\n";
        }
    }

    private function printResults(): void
    {
        echo "========================================\n";
        echo "        LARAVEL RESULTS\n";
        echo "========================================\n\n";

        foreach ($this->results as $name => $result) {
            echo ucfirst(str_replace('_', ' ', $name)) . ":\n";
            foreach ($result as $key => $value) {
                echo "   {$key}: {$value}\n";
            }
            echo "\n";
        }
    }
}

$benchmark = new LaravelBenchmark();
$benchmark->run();
BENCHMARK

echo "Running Laravel benchmark..."
php benchmark.php 2>&1
