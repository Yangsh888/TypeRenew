<?php

namespace Typecho\Cache;

interface Driver
{
    public function name(): string;

    public function available(): bool;

    /**
     * 最近一次失败原因, 供后台展示; 无失败时返回空串
     */
    public function lastError(): string;

    public function get(string $key, ?bool &$hit = null);

    public function set(string $key, $value, int $ttl): bool;

    public function add(string $key, $value, int $ttl): bool;

    public function increment(string $key, int $step = 1, int $initial = 1): ?int;

    public function delete(string $key): bool;

    public function clear(string $prefix): int;

    public function count(string $prefix): int;
}
