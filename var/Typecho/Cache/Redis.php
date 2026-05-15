<?php

namespace Typecho\Cache;

class Redis implements Driver
{
    private array $config;
    private ?\Redis $client = null;
    private bool $connected = false;
    private bool $usable = false;
    private float $lastFailure = 0.0;
    private int $failureCount = 0;
    private const BACKOFF_STEPS = [5, 15, 60, 120, 300];

    public function __construct(array $config)
    {
        $this->config = $config;
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

        if (strpos($value, '{"t":') !== 0) {
            return null;
        }

        $ok = false;
        $decoded = $this->decode((string) $value, $ok);
        if (!$ok) {
            return null;
        }

        $hit = true;
        return $decoded;
    }

    public function set(string $key, $value, int $ttl): bool
    {
        $client = $this->connect();
        if (!$client) {
            return false;
        }

        $payload = $this->encode($value);
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

        $payload = $this->encode($value);
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
            if (!$client->set($key, (string) $initial, ['nx'])) {
                return (int) $client->incrBy($key, max(1, $step));
            }

            return (int) $initial;
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
        $maxRounds = 3;

        for ($round = 0; $round < $maxRounds; $round++) {
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
                $cooldown = $this->getBackoffCooldown();
                if ((microtime(true) - $this->lastFailure) < $cooldown) {
                    return null;
                }
            }
        }

        $this->connected = true;
        $this->usable = false;

        if (!extension_loaded('redis') || !class_exists('\Redis')) {
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
                $this->markUnavailable();
                return null;
            }

            if ($password !== '' && !$client->auth($password)) {
                $this->markUnavailable();
                return null;
            }

            if ($database >= 0 && !$client->select($database)) {
                $this->markUnavailable();
                return null;
            }

            $this->client = $client;
            $this->usable = true;
            $this->lastFailure = 0.0;
            $this->failureCount = 0;
            return $this->client;
        } catch (\Throwable $e) {
            $this->markUnavailable();
            return null;
        }
    }

    private function getBackoffCooldown(): int
    {
        $index = min($this->failureCount, count(self::BACKOFF_STEPS) - 1);
        return self::BACKOFF_STEPS[$index];
    }

    private function encode($value): string
    {
        if ($value instanceof \stdClass) {
            return $this->json(['t' => 'object', 'v' => (array) $value]);
        }

        if (is_array($value)) {
            return $this->json(['t' => 'array', 'v' => $value]);
        }

        if (is_int($value)) {
            return $this->json(['t' => 'int', 'v' => $value]);
        }

        if (is_float($value)) {
            return $this->json(['t' => 'float', 'v' => $value]);
        }

        if (is_bool($value)) {
            return $this->json(['t' => 'bool', 'v' => $value]);
        }

        if ($value === null) {
            return $this->json(['t' => 'null', 'v' => null]);
        }

        return $this->json(['t' => 'string', 'v' => (string) $value]);
    }

    private function decode(string $payload, bool &$ok)
    {
        $ok = false;
        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || !isset($decoded['t'])) {
            return null;
        }

        switch ($decoded['t']) {
            case 'object':
                if (!is_array($decoded['v'] ?? null)) {
                    return null;
                }
                $ok = true;
                return (object) $decoded['v'];
            case 'array':
                $ok = true;
                return is_array($decoded['v'] ?? null) ? $decoded['v'] : [];
            case 'int':
                $ok = true;
                return (int) ($decoded['v'] ?? 0);
            case 'float':
                $ok = true;
                return (float) ($decoded['v'] ?? 0);
            case 'bool':
                $ok = true;
                return (bool) ($decoded['v'] ?? false);
            case 'null':
                $ok = true;
                return null;
            case 'string':
                $ok = true;
                return (string) ($decoded['v'] ?? '');
            default:
                return null;
        }
    }

    private function json(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '{"t":"null","v":null}';
    }

    private function markUnavailable(): void
    {
        $this->client = null;
        $this->usable = false;
        $this->lastFailure = microtime(true);
        $this->failureCount = min($this->failureCount + 1, count(self::BACKOFF_STEPS));
    }
}
