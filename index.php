<?php

if (!defined('__TYPECHO_ROOT_DIR__') && !@include_once 'config.inc.php') {
    file_exists('./install.php') ? header('Location: install.php') : print('Missing Config File');
    exit;
}

\Widget\Init::alloc();

\Typecho\Plugin::factory('index.php')->call('begin');

\Typecho\Router::dispatch();

\Typecho\Plugin::factory('index.php')->call('end');
