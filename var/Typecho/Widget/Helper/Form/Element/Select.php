<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Select extends Element
{
    /**
     * 选择值
     *
     * @var array
     */
    private array $options = [];

    public function input(?string $name = null, ?array $options = null): ?Layout
    {
        $input = new Layout('select');
        $this->container($input->setAttribute('name', $name)
            ->setAttribute('id', $name . '-0-' . self::$uniqueId));
        $this->label->setAttribute('for', $name . '-0-' . self::$uniqueId);
        $this->inputs[] = $input;

        foreach ($options as $value => $label) {
            $this->options[$value] = new Layout('option');
            $input->addItem($this->options[$value]->setAttribute('value', $value)->html($label));
        }

        return $input;
    }

    protected function inputValue($value)
    {
        foreach ($this->options as $option) {
            $option->removeAttribute('selected');
        }

        if (isset($value) && isset($this->options[$value])) {
            $this->options[$value]->setAttribute('selected', 'true');
        }
    }
}
