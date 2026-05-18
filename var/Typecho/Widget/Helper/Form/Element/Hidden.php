<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Hidden extends Element
{
    use TextInputTrait;

    public function init()
    {
        $this->setAttribute('style', 'display:none');
    }

    protected function filterValue(string $value): string
    {
        return htmlspecialchars($value);
    }

    protected function getType(): string
    {
        return 'hidden';
    }
}
