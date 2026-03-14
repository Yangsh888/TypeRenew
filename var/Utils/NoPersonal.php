<?php

namespace Utils;

use Typecho\Widget\Helper\Form;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

trait NoPersonal
{
    public static function personalConfig(Form $form)
    {
        unset($form);
    }
}
