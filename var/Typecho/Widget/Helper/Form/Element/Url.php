<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Common;
use Typecho\Widget\Helper\Form\Element;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Url extends Element
{
    use TextInputTrait;

    protected function filterValue(string $value): string
    {
        return htmlspecialchars(Common::idnToUtf8($value));
    }

    protected function getType(): string
    {
        return 'url';
    }
}
