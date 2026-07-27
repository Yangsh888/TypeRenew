<?php

namespace Typecho;

class Config extends \stdClass implements \Iterator, \ArrayAccess
{
    private array $currentConfig = [];

    public function __construct($config = [])
    {
        $this->setDefault($config);
    }

    public static function factory($config = []): Config
    {
        return new self($config);
    }

    public function setDefault($config, bool $replace = false)
    {
        if (empty($config)) {
            return;
        }

        if (is_string($config)) {
            parse_str($config, $params);
        } else {
            $params = $config;
        }

        foreach ($params as $name => $value) {
            if ($replace || !array_key_exists($name, $this->currentConfig)) {
                $this->currentConfig[$name] = $value;
            }
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->currentConfig);
    }

    public function rewind(): void
    {
        reset($this->currentConfig);
    }

    public function current(): mixed
    {
        return current($this->currentConfig);
    }

    public function next(): void
    {
        next($this->currentConfig);
    }

    public function key(): mixed
    {
        return key($this->currentConfig);
    }

    public function valid(): bool
    {
        return null !== $this->key();
    }

    public function __get(string $name)
    {
        return $this->offsetGet($name);
    }

    public function __set(string $name, $value)
    {
        $this->offsetSet($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->offsetExists($name);
    }

    /**
     * 模板里 $config->name() 直接输出值, 保持与 Typecho 原生模板 API 一致
     * 移除此方法会让 admin/media.php 与第三方主题的 $attachment->url() 全部致命报错
     */
    public function __call(string $name, array $args): void
    {
        $value = $this->offsetGet($name);

        if (is_scalar($value)) {
            echo $value;
        }
    }

    public function __toString(): string
    {
        return Common::jsonEncode($this->currentConfig, 0, '{}');
    }

    public function toArray(): array
    {
        return $this->currentConfig;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->currentConfig[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->currentConfig[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->currentConfig[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->currentConfig[$offset]);
    }
}
