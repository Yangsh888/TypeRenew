<?php

namespace Typecho;

use Typecho\Cache\Apcu;
use Typecho\Cache\Driver;
use Typecho\Cache\Redis;
use Typecho\Db\Query;

class Cache
{
    private static ?self $instance = null;
    private bool $enabled = false;
    private int $ttl = 300;
    private string $prefix = 'typerenew:cache:';
    private ?Driver $driver = null;
    private int $opCount = 0;
    private float $opDuration = 0.0;
    private int $namespaceVersion = 1;
    private float $namespaceSyncAt = 0.0;
    private int $panelCountCache = 0;
    private float $panelCountAt = 0.0;
    private array $tableVersions = [];
    private array $tableSyncAt = [];
    private array $tableAccessOrder = [];
    private const MAX_TABLE_TRACK = 32;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function init(array $config): void
    {
        self::getInstance()->setup($config);
    }

    public function setup(array $config): void
    {
        $status = !empty($config['status']);
        $driver = strtolower((string) ($config['driver'] ?? 'redis'));
        $ttl = (int) ($config['ttl'] ?? 300);
        $prefix = (string) ($config['prefix'] ?? 'typerenew:cache:');

        $this->enabled = $status;
        $this->ttl = max(1, $ttl);
        $this->prefix = $this->sanitizePrefix($prefix);
        $this->driver = null;
        $this->namespaceVersion = 1;
        $this->namespaceSyncAt = 0.0;
        $this->panelCountCache = 0;
        $this->panelCountAt = 0.0;
        $this->tableVersions = [];
        $this->tableSyncAt = [];

        if (!$this->enabled) {
            return;
        }

        if ($driver === 'redis') {
            $instance = new Redis([
                'host' => (string) ($config['redisHost'] ?? '127.0.0.1'),
                'port' => (int) ($config['redisPort'] ?? 6379),
                'password' => (string) ($config['redisPassword'] ?? ''),
                'database' => (int) ($config['redisDatabase'] ?? 0),
                'timeout' => 1.0
            ]);
        } else {
            $instance = new Apcu();
        }

        if ($instance->available()) {
            $this->driver = $instance;
            $this->loadNamespaceVersion();
        } else {
            $this->enabled = false;
        }
    }

    public function enabled(): bool
    {
        return $this->enabled && $this->driver !== null;
    }

    public function get(string $key, ?bool &$hit = null)
    {
        $hit = false;
        if (!$this->enabled()) {
            return null;
        }

        $started = microtime(true);
        $value = $this->driver->get($this->key($key), $hit);
        $this->track($started);
        return $value;
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $started = microtime(true);
        $ok = $this->driver->set($this->key($key), $value, $ttl ?? $this->ttl);
        $this->track($started);
        return $ok;
    }

    public function delete(string $key): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $started = microtime(true);
        $ok = $this->driver->delete($this->key($key));
        $this->track($started);
        return $ok;
    }

    public function tryLock(string $key, int $ttl = 2): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $lockKey = '__lock:' . sha1($key);
        $started = microtime(true);
        $ok = $this->driver->add($this->key($lockKey), '1', max(1, $ttl));
        $this->track($started);
        return $ok;
    }

    public function unlock(string $key): void
    {
        if (!$this->enabled()) {
            return;
        }

        $lockKey = '__lock:' . sha1($key);
        $started = microtime(true);
        $this->driver->delete($this->key($lockKey));
        $this->track($started);
    }

    public function waitFor(string $key, &$value, int $attempts = 2, int $sleepUs = 5000): bool
    {
        $value = null;
        if (!$this->enabled()) {
            return false;
        }

        $attempts = max(1, $attempts);
        for ($i = 0; $i < $attempts; $i++) {
            if ($sleepUs > 0) {
                usleep($sleepUs);
            }

            $hit = false;
            $cached = $this->get($key, $hit);
            if ($hit) {
                $value = $cached;
                return true;
            }
        }

        return false;
    }

    public function flush(): int
    {
        if (!$this->driver) {
            return 0;
        }

        $started = microtime(true);
        $count = $this->driver->clear($this->prefix);
        $this->rotateNamespace();
        $this->track($started);
        return $count;
    }

    public function count(): int
    {
        if (!$this->driver) {
            return 0;
        }

        $started = microtime(true);
        $count = $this->driver->count($this->namespacePrefix());
        $this->track($started);
        return $count;
    }

    public function invalidate(?string $table = null): void
    {
        if (!$this->driver) {
            return;
        }

        $name = $this->normalizeTableName($table);
        if ($name !== null) {
            $version = $this->bumpVersion($this->tableMetaKey($name), $this->tableVersions[$name] ?? 1);
            $this->tableVersions[$name] = $version;
            $this->tableSyncAt[$name] = microtime(true);
            $this->panelCountAt = 0.0;
            return;
        }

        if ($this->namespaceVersion >= 100000) {
            $this->rotateNamespace();
            return;
        }

        $this->rotateNamespace();
    }

    public function queryKey($query): ?string
    {
        $sql = $this->querySql($query);
        if ($sql === null) {
            return null;
        }

        $trimmed = trim((string) preg_replace('/\s+/', ' ', $sql));
        if (!preg_match('/^(SELECT|WITH)\s/i', $trimmed)) {
            return null;
        }

        $tables = $this->queryTables($query, $trimmed);
        if (!empty($tables)) {
            $versionParts = [];
            foreach ($tables as $table) {
                $versionParts[] = $table . '.' . $this->loadTableVersion($table, false);
            }
            sort($versionParts, SORT_STRING);
            return 'sql:' . implode(',', $versionParts) . ':' . sha1($trimmed);
        }

        return 'sql:' . sha1($trimmed);
    }

    public function panel(): array
    {
        $driver = $this->driver ? $this->driver->name() : 'None';
        if ($this->enabled()) {
            $now = microtime(true);
            if (($now - $this->panelCountAt) >= 10.0) {
                $this->panelCountCache = $this->count();
                $this->panelCountAt = $now;
            }
        } else {
            $this->panelCountCache = 0;
            $this->panelCountAt = 0.0;
        }

        return [
            'status' => $this->enabled() ? 'enabled' : 'disabled',
            'driver' => $driver,
            'avg' => $this->opCount > 0 ? round(($this->opDuration * 1000) / $this->opCount, 2) : 0,
            'count' => $this->panelCountCache,
            'ttl' => $this->ttl,
            'prefix' => $this->prefix
        ];
    }

    private function key(string $key): string
    {
        $this->syncNamespaceVersion(false);
        return $this->namespacePrefix() . $key;
    }

    private function namespacePrefix(): string
    {
        return $this->prefix . 'v' . $this->namespaceVersion . ':';
    }

    private function metaKey(): string
    {
        return $this->prefix . '__version';
    }

    private function loadNamespaceVersion(): void
    {
        if (!$this->driver) {
            return;
        }

        $hit = false;
        $version = $this->driver->get($this->metaKey(), $hit);
        if ($hit && is_numeric($version) && (int) $version > 0) {
            $this->namespaceVersion = (int) $version;
            $this->namespaceSyncAt = microtime(true);
            return;
        }

        $this->namespaceVersion = 1;
        $this->namespaceSyncAt = microtime(true);
        $this->driver->set($this->metaKey(), $this->namespaceVersion, 0);
    }

    private function syncNamespaceVersion(bool $force): void
    {
        if (!$this->driver) {
            return;
        }

        $now = microtime(true);
        if (!$force && ($now - $this->namespaceSyncAt) < 1.0) {
            return;
        }

        $hit = false;
        $version = $this->driver->get($this->metaKey(), $hit);
        if ($hit && is_numeric($version) && (int) $version > 0) {
            $this->namespaceVersion = (int) $version;
        }

        $this->namespaceSyncAt = $now;
    }

    private function queryTables($query, string $sql): array
    {
        $tables = [];
        if ($query instanceof Query) {
            $table = $this->normalizeTableName($query->getAttribute('table'));
            if ($table !== null) {
                $tables[$table] = true;
            }
        }

        $sql = substr($sql, 0, 4096);

        if (preg_match_all('/\b(?:FROM|JOIN)\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $table = $this->normalizeTableName($match[1]);
                if ($table !== null) {
                    $tables[$table] = true;
                }
            }
        }

        return array_keys($tables);
    }

    private function normalizeTableName(?string $table): ?string
    {
        if (!$table) {
            return null;
        }

        $name = trim((string) $table);
        if ($name === '') {
            return null;
        }

        $name = str_replace('`', '', $name);
        $parts = preg_split('/\s+/', $name);
        $name = $parts[0] ?? $name;
        $name = explode(',', $name)[0] ?? $name;
        if (strpos($name, '.') !== false) {
            $dotParts = explode('.', $name);
            $name = end($dotParts) ?: $name;
        }

        if (strpos($name, 'table.') === 0) {
            $name = substr($name, 6);
        }

        $name = strtolower($name);
        if ($name === '' || !preg_match('/^[a-z0-9_]+$/', $name)) {
            return null;
        }

        return $name;
    }

    private function loadTableVersion(string $table, bool $force): int
    {
        if (!$this->driver) {
            return 1;
        }

        $now = microtime(true);
        $last = $this->tableSyncAt[$table] ?? 0.0;
        if (!$force && isset($this->tableVersions[$table]) && ($now - $last) < 1.0) {
            return $this->tableVersions[$table];
        }

        $this->pruneTableTracking($table);

        $hit = false;
        $version = $this->driver->get($this->tableMetaKey($table), $hit);
        if ($hit && is_numeric($version) && (int) $version > 0) {
            $this->tableVersions[$table] = (int) $version;
        } else {
            $this->tableVersions[$table] = 1;
            $this->driver->set($this->tableMetaKey($table), 1, 0);
        }

        $this->tableSyncAt[$table] = $now;
        return $this->tableVersions[$table];
    }

    private function pruneTableTracking(string $newTable): void
    {
        if (count($this->tableVersions) < self::MAX_TABLE_TRACK) {
            return;
        }

        unset($this->tableVersions[$newTable], $this->tableSyncAt[$newTable]);

        $keys = array_keys($this->tableVersions);
        $evict = (int) floor(self::MAX_TABLE_TRACK / 4);
        for ($i = 0; $i < $evict && $i < count($keys); $i++) {
            $key = $keys[$i];
            unset($this->tableVersions[$key], $this->tableSyncAt[$key]);
        }
    }

    private function bumpVersion(string $metaKey, int $fallback): int
    {
        if (!$this->driver) {
            return max(1, $fallback);
        }

        $next = $this->driver->increment($metaKey, 1, max(1, $fallback + 1));
        if ($next === null || $next < 1) {
            $next = max(1, $fallback + 1);
            $this->driver->set($metaKey, $next, 0);
        }

        return $next;
    }

    private function rotateNamespace(): void
    {
        $this->namespaceVersion = $this->bumpVersion($this->metaKey(), $this->namespaceVersion);
        $this->namespaceSyncAt = microtime(true);
        $this->panelCountCache = 0;
        $this->panelCountAt = microtime(true);
        $this->tableVersions = [];
        $this->tableSyncAt = [];
    }

    private function tableMetaKey(string $table): string
    {
        return $this->prefix . '__table:' . $table;
    }

    private function querySql($query): ?string
    {
        if ($query instanceof Query) {
            $action = $query->getAttribute('action');
            if ($action !== Db::SELECT) {
                return null;
            }

            return $query->prepare((string) $query);
        }

        if (is_string($query)) {
            return $query;
        }

        return null;
    }

    private function sanitizePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            $prefix = 'typerenew:cache:';
        }

        $prefix = preg_replace('/[^a-zA-Z0-9:_-]/', '', $prefix) ?? 'typerenew:cache:';
        return rtrim($prefix, ':') . ':';
    }

    private function track(float $started): void
    {
        $this->opCount++;
        $this->opDuration += max(0.0, microtime(true) - $started);
    }
}
