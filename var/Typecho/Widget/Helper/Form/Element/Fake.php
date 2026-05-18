<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Fake extends Element
{
    public function __construct(string $name, $value)
    {
        $this->name = $name;
        self::$uniqueId++;

        $this->init();

        $this->input = $this->input($name);

        if (null !== $value) {
            $this->value($value);
        }
    }

    public function input(?string $name = null, ?array $options = null): ?Layout
    {
        $input = new Layout('input');
        $this->inputs[] = $input;
        return $input;
    }

    protected function inputValue($value)
    {
        $this->input->setAttribute('value', $value);
    }
}
