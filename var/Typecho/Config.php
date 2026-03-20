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

    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->currentConfig);
    }

    public function next(): void
    {
        next($this->currentConfig);
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->currentConfig);
    }

    public function valid(): bool
    {
        return false !== $this->current();
    }

    public function __get(string $name)
    {
        return $this->offsetGet($name);
    }

    public function __set(string $name, $value)
    {
        $this->offsetSet($name, $value);
    }

    public function __call(string $name, ?array $args)
    {
        echo $this->currentConfig[$name];
    }

    public function __isSet(string $name): bool
    {
        return $this->offsetExists($name);
    }

    public function __toString(): string
    {
        return json_encode($this->currentConfig);
    }

    public function toArray(): array
    {
        return $this->currentConfig;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->currentConfig[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->currentConfig[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->currentConfig[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->currentConfig[$offset]);
    }
}
