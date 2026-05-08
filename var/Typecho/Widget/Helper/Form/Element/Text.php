<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Text extends Element
{
    use TextInputTrait;

    protected function filterValue(string $value): string
    {
        return htmlspecialchars($value);
    }

    protected function getType(): string
    {
        return 'text';
    }
}
