<?php

namespace Typecho\Plugin;

use Typecho\Widget\Helper\Form;

/**
 * 插件接口
 *
 * @package Plugin
 * @abstract
 */
interface PluginInterface
{
    public static function activate();

    public static function deactivate();

    public static function config(Form $form);

    public static function personalConfig(Form $form);
}
