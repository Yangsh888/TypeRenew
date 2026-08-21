<?php

namespace Typecho\Cache;

class Redis implements Driver
{
    private ?\Redis $client = null;
    private bool $connected = false;
    private bool $usable = false;
    private float $lastFailure = 0.0;
    private int $failureCount = 0;
    private string $lastError = '';
    private const BACKOFF_STEPS = [5, 15];
    private const INCREMENT_SCRIPT = <<<'LUA'
local value = redis.call('GET', KEYS[1])
if not value then
    redis.call('SET', KEYS[1], ARGV[2])
    return tonumber(ARGV[2])
end
local number = tonumber(value)
if not number then
    redis.call('SET', KEYS[1], ARGV[2])
    return tonumber(ARGV[2])
end
number = number + tonumber(ARGV[1])
redis.call('SET', KEYS[1], tostring(number))
return number
LUA;

    public function __construct(private array $config)
    {
    }

    public function name(): string
    {
        return 'Redis';
    }

    public function available(): bool
    {
        $this->connect();
        return $this->usable;
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function get(string $key, ?bool &$hit = null)
    {
        $hit = false;
        $client = $this->connect();
        if (!$client) {
            return null;
        }

        try {
            $value = $client->get($key);
        } catch (\Throwable $e) {
            $this->markUnavailable();
            return null;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^-?[0-9]+$/D', $value)) {
            $hit = true;
            return (int) $value;
        }

        $result = @unserialize($value);
        if ($result === false && $value !== serialize(false)) {
            return null;
        }

        $hit = true;
        return $result;
    }

    public function set(string $key, $value, int $ttl): bool
    {
        $client = $this->connect();
        if (!$client) {
            return false;
        }

        $payload = is_int($value) ? (string) $value : serialize($value);
        try {
            if ($ttl > 0) {
                return (bool) $client->setex($key, $ttl, $payload);
            }

            return (bool) $client->set($key, $payload);
        } catch (\Throwable $e) {
            $this->markUnavailable();
            return false;
        }
    }

    public function add(string $key, $value, int $ttl): bool
    {
        $client = $this->connect();
        if (!$client) {
            return false;
        }

        $payload = serialize($value);
        try {
            return (bool) $client->set($key, $payload, ['nx', 'ex' => max(1, $ttl)]);
        } catch (\Throwable $e) {
            $this->markUnavailable();
            return false;
        }
    }

    public function increment(string $key, int $step = 1, int $initial = 1): ?int
    {
        $client = $this->connect();
        if (!$client) {
            return null;
        }

        try {
            $next = $client->eval(
                self::INCREMENT_SCRIPT,
                [$key, (string) max(1, $step), (string) max(1, $initial)],
                1
            );
            return is_int($next) ? $next : (is_numeric($next) ? (int) $next : null);
        } catch (\Throwable $e) {
            $this->markUnavailable();
            return null;
        }
    }

    public function delete(string $key): bool
    {
        $client = $this->connect();
        if (!$client) {
            return false;
        }

        try {
            return (int) $client->del([$key]) > 0;
        } catch (\Throwable $e) {
            $this->markUnavailable();
            return false;
        }
    }

    public function clear(string $prefix): int
    {
        $client = $this->connect();
        if (!$client || $prefix === '') {
            return 0;
        }

        $total = 0;
        $pattern = $prefix . '*';

        for ($round = 0; $round < 3; $round++) {
            $roundDeleted = $this->scanAndDelete($client, $pattern);
            $total += $roundDeleted;
            if ($roundDeleted === 0) {
                break;
            }
        }

        return $total;
    }

    private function scanAndDelete(\Redis $client, string $pattern): int
    {
        $total = 0;
        $it = null;
        $failed = 0;

        while (true) {
            try {
                $keys = $client->scan($it, $pattern, 500);
            } catch (\Throwable $e) {
                $this->markUnavailable();
                break;
            }

            if ($keys === false) {
                $failed++;
                if ($failed >= 3) {
                    break;
                }
                if ($it === 0 || $it === '0') {
                    break;
                }
                continue;
            }
            $failed = 0;

            if (is_array($keys) && !empty($keys)) {
                try {
                    $total += (int) $client->del($keys);
                } catch (\Throwable $e) {
                    $this->markUnavailable();
                    break;
                }
            }
            if ($it === 0 || $it === '0') {
                break;
            }
        }

        return $total;
    }

    public function count(string $prefix): int
    {
        $client = $this->connect();
        if (!$client || $prefix === '') {
            return 0;
        }

        $total = 0;
        $it = null;
        $pattern = $prefix . '*';
        $failed = 0;

        while (true) {
            try {
                $keys = $client->scan($it, $pattern, 500);
            } catch (\Throwable $e) {
                $this->markUnavailable();
                break;
            }

            if ($keys === false) {
                $failed++;
                if ($failed >= 3) {
                    break;
                }
                if ($it === 0 || $it === '0') {
                    break;
                }
                continue;
            }
            $failed = 0;

            if (is_array($keys) && !empty($keys)) {
                $total += count($keys);
            }
            if ($it === 0 || $it === '0') {
                break;
            }
        }

        return $total;
    }

    private function connect(): ?\Redis
    {
        if ($this->connected) {
            if ($this->usable && $this->client !== null) {
                return $this->client;
            }
            if ($this->lastFailure > 0) {
                $cooldown = self::BACKOFF_STEPS[min($this->failureCount, count(self::BACKOFF_STEPS) - 1)];
                if ((microtime(true) - $this->lastFailure) < $cooldown) {
                    return null;
                }
            }
        }

        $this->connected = true;
        $this->usable = false;

        if (!extension_loaded('redis') || !class_exists('\Redis')) {
            $this->lastError = 'PHP redis 扩展未安装';
            return null;
        }

        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 6379);
        $password = (string) ($this->config['password'] ?? '');
        $database = (int) ($this->config['database'] ?? 0);
        $timeout = (float) ($this->config['timeout'] ?? 1.0);
        $readTimeout = (float) ($this->config['readTimeout'] ?? 2.0);

        try {
            $client = new \Redis();
            if (!$client->connect($host, $port, $timeout, null, 0, $readTimeout)) {
                $this->markUnavailable(_t('无法连接 %s:%d', $host, $port));
                return null;
            }

            if ($password !== '' && !$client->auth($password)) {
                $this->markUnavailable(_t('密码认证失败'));
                return null;
            }

            if ($database >= 0 && !$client->select($database)) {
                $this->markUnavailable(_t('无法选择数据库 %d', $database));
                return null;
            }

            $this->client = $client;
            $this->usable = true;
            $this->lastFailure = 0.0;
            $this->failureCount = 0;
            $this->lastError = '';
            return $this->client;
        } catch (\Throwable $e) {
            $this->markUnavailable($e->getMessage());
            return null;
        }
    }

    private function markUnavailable(string $reason = ''): void
    {
        $this->client = null;
        $this->usable = false;
        $this->lastFailure = microtime(true);
        $this->failureCount = min($this->failureCount + 1, count(self::BACKOFF_STEPS));

        if ($reason !== '') {
            $this->lastError = mb_substr(trim($reason), 0, 200, 'UTF-8');
        }
    }
}
