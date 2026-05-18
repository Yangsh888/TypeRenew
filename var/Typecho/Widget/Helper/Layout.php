<?php

namespace Typecho\Widget\Helper;

class Layout
{
    private array $items = [];

    private array $attributes = [];

    private string $tagName = 'div';

    private bool $close = false;

    private ?bool $forceClose = null;

    private string $html = '';

    private $parent;

    public function __construct(string $tagName = 'div', ?array $attributes = null)
    {
        $this->setTagName($tagName);

        if (!empty($attributes)) {
            foreach ($attributes as $attributeName => $attributeValue) {
                $this->setAttribute($attributeName, (string)$attributeValue);
            }
        }
    }

    public function setAttribute(string $attributeName, $attributeValue): Layout
    {
        $this->attributes[$attributeName] = (string) $attributeValue;
        return $this;
    }

    public function removeItem(Layout $item): Layout
    {
        unset($this->items[array_search($item, $this->items)]);
        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }

    public function setTagName(string $tagName)
    {
        $this->tagName = $tagName;
    }

    public function removeAttribute(string $attributeName): Layout
    {
        if (isset($this->attributes[$attributeName])) {
            unset($this->attributes[$attributeName]);
        }

        return $this;
    }

    public function getAttribute(string $attributeName): ?string
    {
        return $this->attributes[$attributeName] ?? null;
    }

    public function setClose(bool $close): Layout
    {
        $this->forceClose = $close;
        return $this;
    }

    public function getParent(): Layout
    {
        return $this->parent;
    }

    public function setParent(Layout $parent): Layout
    {
        $this->parent = $parent;
        return $this;
    }

    public function appendTo(Layout $parent): Layout
    {
        $parent->addItem($this);
        return $this;
    }

    public function addItem(Layout $item): Layout
    {
        $item->setParent($this);
        $this->items[] = $item;
        return $this;
    }

    public function __get(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = (string) $value;
    }

    public function render()
    {
        if (empty($this->items) && empty($this->html)) {
            $this->close = true;
        }

        if (null !== $this->forceClose) {
            $this->close = $this->forceClose;
        }

        $this->start();
        $this->html();
        $this->end();
    }

    public function start()
    {
        echo $this->tagName ? "<{$this->tagName}" : null;

        foreach ($this->attributes as $attributeName => $attributeValue) {
            echo " {$attributeName}=\"{$attributeValue}\"";
        }

        if (!$this->close && $this->tagName) {
            echo ">\n";
        }
    }

    public function html(?string $html = null)
    {
        if (null === $html) {
            if (empty($this->html)) {
                foreach ($this->items as $item) {
                    $item->render();
                }
            } else {
                echo $this->html;
            }
        } else {
            $this->html = $html;
            return $this;
        }
    }

    public function end()
    {
        if ($this->tagName) {
            echo $this->close ? " />\n" : "</{$this->tagName}>\n";
        }
    }
}
