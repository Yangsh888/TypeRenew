<?php

namespace Typecho\Plugin;

use Typecho\Widget\Helper\Form;

interface PluginInterface
{
    public static function activate();

    public static function deactivate();

    public static function config(Form $form);

    public static function personalConfig(Form $form);
}
