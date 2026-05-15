<?php

namespace Typecho\Cache;

class Apcu implements Driver
{
    public function name(): string
    {
        return 'APCu';
    }

    public function available(): bool
    {
        if (!extension_loaded('apcu') || !function_exists('apcu_enabled')) {
            return false;
        }

        return (bool) apcu_enabled();
    }

    public function get(string $key, ?bool &$hit = null)
    {
        $hit = false;
        if (!$this->available()) {
            return null;
        }

        $success = false;
        $value = apcu_fetch($key, $success);
        $hit = (bool) $success;
        return $hit ? $value : null;
    }

    public function set(string $key, $value, int $ttl): bool
    {
        if (!$this->available()) {
            return false;
        }

        return (bool) apcu_store($key, $value, max(0, $ttl));
    }

    public function add(string $key, $value, int $ttl): bool
    {
        if (!$this->available()) {
            return false;
        }

        return (bool) apcu_add($key, $value, max(1, $ttl));
    }

    public function increment(string $key, int $step = 1, int $initial = 1): ?int
    {
        if (!$this->available()) {
            return null;
        }

        $ok = false;
        if (!apcu_exists($key)) {
            if (apcu_add($key, $initial, 0)) {
                return $initial;
            }
        }

        $next = apcu_inc($key, max(1, $step), $ok);
        return $ok ? (int) $next : null;
    }

    public function delete(string $key): bool
    {
        if (!$this->available()) {
            return false;
        }

        return (bool) apcu_delete($key);
    }

    public function clear(string $prefix): int
    {
        if (!$this->available() || $prefix === '') {
            return 0;
        }

        $keys = $this->keys($prefix);
        if (empty($keys)) {
            return 0;
        }

        $total = 0;
        foreach (array_chunk($keys, 100) as $chunk) {
            $deleted = apcu_delete($chunk);
            $total += is_array($deleted) ? count($deleted) : (int) $deleted;
        }
        return $total;
    }

    public function count(string $prefix): int
    {
        if (!$this->available() || $prefix === '') {
            return 0;
        }

        return count($this->keys($prefix));
    }

    private function keys(string $prefix): array
    {
        if (!class_exists('\APCUIterator')) {
            return [];
        }

        $regex = '/^' . preg_quote($prefix, '/') . '/';
        $iterator = new \APCUIterator($regex, APC_ITER_KEY);
        $keys = [];

        foreach ($iterator as $item) {
            if (!empty($item['key'])) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }
}
