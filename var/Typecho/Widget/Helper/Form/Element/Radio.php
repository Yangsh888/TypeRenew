<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Radio extends Element
{
    private array $options = [];

    public function input(?string $name = null, ?array $options = null): ?Layout
    {
        foreach ($options as $value => $label) {
            $this->options[$value] = new Layout('input');
            $item = $this->multiline();
            $id = $this->name . '-' . $this->filterValue($value);
            $this->inputs[] = $this->options[$value];

            $item->addItem($this->options[$value]->setAttribute('name', $this->name)
                ->setAttribute('type', 'radio')
                ->setAttribute('value', $value)
                ->setAttribute('id', $id));

            $labelItem = new Layout('label');
            $item->addItem($labelItem->setAttribute('for', $id)->html($label));
            $this->container($item);
        }

        return current($this->options) ?: null;
    }

    protected function inputValue($value)
    {
        foreach ($this->options as $option) {
            $option->removeAttribute('checked');
        }

        if (isset($value) && isset($this->options[$value])) {
            $this->value = $value;
            $this->options[$value]->setAttribute('checked', 'true');
            $this->input = $this->options[$value];
        }
    }
}
