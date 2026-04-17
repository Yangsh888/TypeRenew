<?php

namespace Utils;

use Typecho\Cache;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Pref
{
    public static function load(
        ?array &$runtime,
        string $cacheKey,
        array $defaults,
        callable $fetchRaw,
        callable $normalize,
        callable $ensureStored,
        callable $report,
        string $ttlKey = 'cacheTtl'
    ): array {
        if (is_array($runtime)) {
            return $runtime;
        }

        $cache = Cache::getInstance();
        if ($cache->enabled()) {
            try {
                $hit = false;
                $cached = $cache->get($cacheKey, $hit);
                if ($hit && is_array($cached)) {
                    $runtime = $cached;
                    return $runtime;
                }
            } catch (\Throwable $e) {
                $report('cache.get', $e);
            }
        }

        $raw = self::fetch($fetchRaw, $report, 'settings.read');
        if (empty($raw)) {
            try {
                $ensureStored();
            } catch (\Throwable $e) {
                $report('settings.ensure', $e);
            }
            $raw = self::fetch($fetchRaw, $report, 'settings.retry');
        }

        $runtime = $normalize(array_merge($defaults, $raw));
        if ($cache->enabled()) {
            try {
                $cache->set($cacheKey, $runtime, max(60, (int) ($runtime[$ttlKey] ?? 300)));
            } catch (\Throwable $e) {
                $report('cache.set', $e);
            }
        }
        return $runtime;
    }

    public static function clear(string $cacheKey, callable $report): void
    {
        try {
            Cache::getInstance()->delete($cacheKey);
        } catch (\Throwable $e) {
            $report('cache.delete', $e);
        }
    }

    public static function sync(
        string $pluginName,
        array $defaults,
        callable $normalize,
        callable $report,
        ?callable $format = null,
        ?callable $fetchRaw = null
    ): array {
        $fetchRaw = $fetchRaw ?? static fn(): array => (array) Helper::options()->plugin($pluginName)->toArray();
        $raw = self::fetch($fetchRaw, $report, 'settings.read');
        $settings = $normalize(array_merge($defaults, $raw));
        $stored = $format ? $format($settings) : $settings;

        try {
            Helper::configPlugin($pluginName, is_array($stored) ? $stored : []);
        } catch (\Throwable $e) {
            $report('settings.store', $e);
        }

        return is_array($stored) ? $stored : [];
    }

    public static function forget(?array &$runtime, string $cacheKey, callable $report): void
    {
        self::clear($cacheKey, $report);
        $runtime = null;
        \Widget\Options::destroy();
    }

    private static function fetch(callable $fetchRaw, callable $report, string $scope): array
    {
        try {
            $value = $fetchRaw();
            return is_array($value) ? $value : [];
        } catch (\Throwable $e) {
            $report($scope, $e);
            return [];
        }
    }
}
