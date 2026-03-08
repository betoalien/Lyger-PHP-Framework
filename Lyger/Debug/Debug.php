<?php

declare(strict_types=1);

namespace Lyger\Debug;

/**
 * Debug - Debugging helper similar to Laravel dd() and dump()
 */
class Debug
{
    private static bool $enabled = true;
    private static array $logs = [];
    private static array $queries = [];
    private static float $startTime;
    private static ?float $lastTime = null;

    public static function start(): void
    {
        self::$startTime = microtime(true);
        self::$lastTime = self::$startTime;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Dump and die - print variable and stop execution
     */
    public static function dd(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            self::dump($var);
        }
        die(1);
    }

    /**
     * Dump - print variable without stopping
     */
    public static function dump(mixed $var): void
    {
        if (!self::$enabled) {
            return;
        }

        $output = self::formatVar($var);

        if (php_sapi_name() === 'cli') {
            echo $output . "\n";
        } else {
            echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 14px;">';
            echo htmlspecialchars($output);
            echo '</pre>';
        }
    }

    /**
     * Log - add to debug log
     */
    public static function log(mixed $var, string $level = 'info'): void
    {
        self::$logs[$level][] = [
            'time' => microtime(true) - self::$startTime,
            'data' => $var,
        ];
    }

    public static function info(mixed $var): void
    {
        self::log($var, 'info');
    }

    public static function warning(mixed $var): void
    {
        self::log($var, 'warning');
    }

    public static function error(mixed $var): void
    {
        self::log($var, 'error');
    }

    public static function debug(mixed $var): void
    {
        self::log($var, 'debug');
    }

    /**
     * Record SQL query
     */
    public static function query(string $sql, array $bindings = [], float $time = 0): void
    {
        self::$queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => $time,
            'timestamp' => microtime(true) - self::$startTime,
        ];
    }

    /**
     * Get recorded logs
     */
    public static function getLogs(): array
    {
        return self::$logs;
    }

    /**
     * Get recorded queries
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }

    /**
     * Get execution time
     */
    public static function getExecutionTime(): float
    {
        return microtime(true) - self::$startTime;
    }

    /**
     * Get memory usage
     */
    public static function getMemoryUsage(): string
    {
        $bytes = memory_get_usage(true);
        return self::formatBytes($bytes);
    }

    /**
     * Get peak memory usage
     */
    public static function getPeakMemoryUsage(): string
    {
        $bytes = memory_get_peak_usage(true);
        return self::formatBytes($bytes);
    }

    /**
     * Measure time between two points
     */
    public static function measure(string $label = ''): float
    {
        $now = microtime(true);
        $elapsed = $now - (self::$lastTime ?? $now);
        self::$lastTime = $now;

        if ($label) {
            self::info("{$label}: " . number_format($elapsed * 1000, 2) . "ms");
        }

        return $elapsed;
    }

    /**
     * Get debug info for toolbar
     */
    public static function getToolbarData(): array
    {
        return [
            'execution_time' => self::getExecutionTime(),
            'memory_usage' => self::getMemoryUsage(),
            'peak_memory' => self::getPeakMemoryUsage(),
            'query_count' => count(self::$queries),
            'queries' => self::$queries,
            'logs' => self::$logs,
        ];
    }

    /**
     * Clear all debug data
     */
    public static function clear(): void
    {
        self::$logs = [];
        self::$queries = [];
    }

    private static function formatVar(mixed $var): string
    {
        if (is_null($var)) {
            return 'null';
        }

        if (is_bool($var)) {
            return $var ? 'true' : 'false';
        }

        if (is_string($var)) {
            return '"' . $var . '"';
        }

        if (is_array($var)) {
            return self::formatArray($var);
        }

        if (is_object($var)) {
            return self::formatObject($var);
        }

        if (is_resource($var)) {
            return 'Resource: ' . get_resource_type($var);
        }

        return (string) $var;
    }

    private static function formatArray(array $array, int $depth = 0): string
    {
        if (empty($array)) {
            return '[]';
        }

        $indent = str_repeat('  ', $depth);
        $output = "[\n";

        foreach ($array as $key => $value) {
            $output .= $indent . '  "' . $key . '" => ';

            if (is_array($value)) {
                if ($depth < 2) {
                    $output .= self::formatArray($value, $depth + 1);
                } else {
                    $output .= '[...]';
                }
            } else {
                $output .= self::formatVar($value);
            }

            $output .= "\n";
        }

        $output .= $indent . ']';
        return $output;
    }

    private static function formatObject(object $obj): string
    {
        $class = get_class($obj);

        if ($obj instanceof \DateTime) {
            return $obj->format('Y-m-d H:i:s');
        }

        if (method_exists($obj, 'toArray')) {
            return $class . ' ' . self::formatArray($obj->toArray());
        }

        if (method_exists($obj, '__toString')) {
            return (string) $obj;
        }

        $reflection = new \ReflectionClass($obj);
        $properties = $reflection->getProperties();

        $output = $class . ' {\n';
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($obj);
            $output .= '  ' . $property->getName() . ': ' . self::formatVar($value) . "\n";
        }
        $output .= '}';

        return $output;
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

/**
 * Timeline - Track execution timeline
 */
class Timeline
{
    private static array $events = [];
    private static float $startTime;

    public static function start(): void
    {
        self::$startTime = microtime(true);
    }

    public static function add(string $name, string $description = ''): void
    {
        self::$events[] = [
            'name' => $name,
            'description' => $description,
            'start' => microtime(true) - self::$startTime,
            'end' => null,
        ];
    }

    public static function end(string $name): void
    {
        $end = microtime(true) - self::$startTime;

        foreach (array_reverse(self::$events) as $index => $event) {
            if ($event['name'] === $name && $event['end'] === null) {
                self::$events[$index]['end'] = $end;
                break;
            }
        }
    }

    public static function getEvents(): array
    {
        return self::$events;
    }
}
