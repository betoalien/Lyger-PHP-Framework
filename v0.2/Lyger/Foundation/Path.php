<?php

declare(strict_types=1);

namespace Lyger\Foundation;

/**
 * Path - Cross-platform path helper
 */
class Path
{
    private static ?string $basePath = null;

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, DIRECTORY_SEPARATOR);
    }

    public static function getBasePath(): string
    {
        if (self::$basePath === null) {
            self::$basePath = self::detectBasePath();
        }
        return self::$basePath;
    }

    private static function detectBasePath(): string
    {
        // Try to detect from composer autoload
        $vendorPath = dirname(__DIR__, 3) . '/vendor/autoload.php';
        if (file_exists($vendorPath)) {
            return dirname(__DIR__, 3);
        }

        // Fallback to current working directory
        return getcwd() ?: dirname(__DIR__);
    }

    public static function resolve(string ...$segments): string
    {
        return self::getBasePath() . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }

    public static function database(string ...$segments): string
    {
        return self::resolve('database', ...$segments);
    }

    public static function storage(string ...$segments): string
    {
        return self::resolve('storage', ...$segments);
    }

    public static function config(string ...$segments): string
    {
        return self::resolve('config', ...$segments);
    }

    public static function resource(string ...$segments): string
    {
        return self::resolve('resources', ...$segments);
    }

    public static function public(string ...$segments): string
    {
        return self::resolve('public', ...$segments);
    }

    public static function app(string ...$segments): string
    {
        return self::resolve('App', ...$segments);
    }

    public static function ensureDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        return true;
    }
}

/**
 * Config - Configuration helper
 */
class Config
{
    private static array $config = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public static function load(string $file): void
    {
        $path = Path::config($file . '.php');
        if (file_exists($path)) {
            $values = require $path;
            if (is_array($values)) {
                self::$config = array_merge(self::$config, $values);
            }
        }
    }

    public static function all(): array
    {
        return self::$config;
    }
}

/**
 * Platform - Platform detection helper
 */
class Platform
{
    public static function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    public static function isMac(): bool
    {
        return PHP_OS === 'DARWIN';
    }

    public static function isLinux(): bool
    {
        return PHP_OS === 'LINUX';
    }

    public static function getOS(): string
    {
        return PHP_OS;
    }

    public static function getExtensionSuffix(): string
    {
        if (self::isWindows()) {
            return 'dll';
        }
        if (self::isMac()) {
            return 'dylib';
        }
        return 'so';
    }

    public static function getLibPrefix(): string
    {
        if (self::isWindows()) {
            return '';
        }
        return 'lib';
    }
}

/**
 * Str - String helper
 */
class Str
{
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    public static function snake(string $value, string $delimiter = '_'): string
    {
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', $value);
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }
        return $value;
    }

    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    public static function slug(string $value): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9-]/u', '-', self::ascii($value)));
    }

    public static function ascii(string $value): string
    {
        return preg_replace('/[^\x00-\x7F]/', '', transliterator_transliterate('Latin-ASCII', $value));
    }

    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    public static function random(int $length = 16): string
    {
        $string = '';
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $string;
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }
}

/**
 * Arr - Array helper
 */
class Arr
{
    public static function get(array $array, string|int $key, mixed $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        if (strpos($key, '.') === false) {
            return $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && isset($array[$segment])) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    public static function set(array &$array, string|int $key, mixed $value): void
    {
        if ($key === null) {
            $array[] = $value;
            return;
        }

        if (strpos($key, '.') === false) {
            $array[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }

    public static function forget(array &$array, string|int $key): void
    {
        if (isset($array[$key])) {
            unset($array[$key]);
            return;
        }

        if (strpos($key, '.') === false) {
            return;
        }

        $keys = explode('.', $key);
        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                return;
            }
            $array = &$array[$key];
        }

        unset($array[array_shift($keys)]);
    }

    public static function has(array $array, string|int $key): bool
    {
        if (isset($array[$key])) {
            return true;
        }

        if (strpos($key, '.') === false) {
            return false;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && isset($array[$segment])) {
                $array = $array[$segment];
            } else {
                return false;
            }
        }

        return true;
    }

    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    public static function pluck(array $array, string $value, ?string $key = null): array
    {
        $result = [];
        foreach ($array as $item) {
            $result[] = is_array($item) ? $item[$value] : $item->$value;
        }
        return $result;
    }

    public static function flatten(array $array, int $depth = -1): array
    {
        $result = [];
        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, self::flatten($item, $depth - 1));
            }
        }
        return $result;
    }
}
