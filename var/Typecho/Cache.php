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
    private string $lastError = '';

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
        $this->lastError = '';

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
            $this->lastError = $instance->lastError();
            $this->enabled = false;
        }
    }

    public function enabled(): bool
    {
        return $this->enabled && $this->driver !== null;
    }

    public function lastError(): string
    {
        return $this->driver !== null ? $this->driver->lastError() : $this->lastError;
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

        unset($table);
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

        return 'sql:' . $this->namespaceVersion . ':' . sha1($trimmed);
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
            'prefix' => $this->prefix,
            'error' => $this->lastError()
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

    private function rotateNamespace(): void
    {
        $this->namespaceVersion = $this->bumpVersion($this->metaKey(), $this->namespaceVersion);
        $this->namespaceSyncAt = microtime(true);
        $this->panelCountCache = 0;
        $this->panelCountAt = microtime(true);
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
