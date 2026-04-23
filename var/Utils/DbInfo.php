<?php

namespace Utils;

class DbInfo
{
    public static function isMariaDb(string $rawVersion): bool
    {
        return stripos($rawVersion, 'mariadb') !== false;
    }

    public static function extractVersion(string $rawVersion): string
    {
        if (preg_match_all('/\d+\.\d+(?:\.\d+)?/', $rawVersion, $matches) !== 1 || empty($matches[0])) {
            return '';
        }

        $versions = $matches[0];
        if (self::isMariaDb($rawVersion)) {
            return (string) end($versions);
        }

        return (string) reset($versions);
    }

    public static function minimumMysqlVersion(string $rawVersion): string
    {
        return self::isMariaDb($rawVersion) ? '10.3.0' : '5.7.0';
    }

    public static function mysqlLabel(string $rawVersion): string
    {
        return self::isMariaDb($rawVersion) ? 'MariaDB' : 'MySQL';
    }

    public static function resolveMysqlCollation(string $charset, string $rawVersion, ?string $existingCollation = null): string
    {
        $charset = strtolower(trim($charset));

        if ($existingCollation !== null) {
            $existingCollation = strtolower(trim($existingCollation));
            if ($existingCollation !== '' && str_starts_with($existingCollation, $charset . '_')) {
                return $existingCollation;
            }
        }

        if ($charset !== 'utf8mb4') {
            return $charset === 'utf8' ? 'utf8_unicode_ci' : $charset . '_unicode_ci';
        }

        if (self::isMariaDb($rawVersion)) {
            return 'utf8mb4_unicode_ci';
        }

        $version = self::extractVersion($rawVersion);
        if ($version !== '' && version_compare($version, '8.0.0', '>=')) {
            return 'utf8mb4_0900_ai_ci';
        }

        return 'utf8mb4_unicode_ci';
    }
}
