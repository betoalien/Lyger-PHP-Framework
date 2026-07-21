<?php

declare(strict_types=1);

namespace Lyger\Foundation;

/**
 * PlatformValidation - Cross-platform compatibility validation
 */
class PlatformValidation
{
    private static array $issues = [];
    private static array $requirements = [
        'php' => [
            'min_version' => '8.0',
            'extensions' => ['pdo', 'json', 'mbstring'],
        ],
        'system' => [
            'os' => ['Linux', 'Darwin', 'WINNT', 'Windows'],
        ],
    ];

    /**
     * Validate platform compatibility
     */
    public static function validate(): array
    {
        self::$issues = [];

        self::validatePhpVersion();
        self::validateExtensions();
        self::validateDirectoryPermissions();
        self::validateDatabaseConnection();

        return [
            'valid' => empty(self::$issues),
            'issues' => self::$issues,
            'platform' => [
                'os' => PHP_OS,
                'php_version' => PHP_VERSION,
                'platform' => PHP_OS_FAMILY,
            ],
        ];
    }

    private static function validatePhpVersion(): void
    {
        $minVersion = self::$requirements['php']['min_version'];
        if (version_compare(PHP_VERSION, $minVersion, '<')) {
            self::$issues[] = [
                'type' => 'error',
                'category' => 'php',
                'message' => "PHP version must be at least {$minVersion}. Current: " . PHP_VERSION,
            ];
        }
    }

    private static function validateExtensions(): void
    {
        foreach (self::$requirements['php']['extensions'] as $ext) {
            if (!extension_loaded($ext)) {
                self::$issues[] = [
                    'type' => 'error',
                    'category' => 'extension',
                    'message' => "Required PHP extension '{$ext}' is not loaded",
                ];
            }
        }

        // Check FFI extension
        if (!extension_loaded('ffi')) {
            self::$issues[] = [
                'type' => 'warning',
                'category' => 'extension',
                'message' => "PHP extension 'ffi' is not loaded. FFI features will be disabled.",
            ];
        }
    }

    private static function validateDirectoryPermissions(): void
    {
        $dirs = [
            'database',
            'storage',
            'storage/logs',
            'storage/queue',
            'storage/cache',
        ];

        foreach ($dirs as $dir) {
            $path = Path::resolve($dir);
            if (!is_dir($path)) {
                if (!@mkdir($path, 0755, true)) {
                    self::$issues[] = [
                        'type' => 'error',
                        'category' => 'permissions',
                        'message' => "Cannot create directory: {$dir}",
                    ];
                }
            } elseif (!is_writable($path)) {
                self::$issues[] = [
                    'type' => 'error',
                    'category' => 'permissions',
                    'message' => "Directory is not writable: {$dir}",
                ];
            }
        }
    }

    private static function validateDatabaseConnection(): void
    {
        $dbPath = Path::database('database.sqlite');
        $dbDir = dirname($dbPath);

        if (!is_dir($dbDir)) {
            return; // Will be created automatically
        }

        if (file_exists($dbPath)) {
            if (!is_readable($dbPath)) {
                self::$issues[] = [
                    'type' => 'error',
                    'category' => 'database',
                    'message' => "Database file is not readable: {$dbPath}",
                ];
            }
        }
    }

    /**
     * Get list of supported OS
     */
    public static function getSupportedOS(): array
    {
        return self::$requirements['system']['os'];
    }

    /**
     * Check if current OS is supported
     */
    public static function isOSSupported(): bool
    {
        return in_array(PHP_OS, self::$requirements['system']['os'], true);
    }

    /**
     * Get current platform info
     */
    public static function getPlatformInfo(): array
    {
        return [
            'os' => PHP_OS,
            'os_family' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'architecture' => PHP_INT_SIZE === 8 ? '64-bit' : '32-bit',
            'extensions' => get_loaded_extensions(),
        ];
    }
}
