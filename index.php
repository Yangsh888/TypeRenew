<?php

if (!defined('__TYPECHO_ROOT_DIR__')) {
    $configFile = __DIR__ . '/config.inc.php';
    if (!is_file($configFile)) {
        file_exists('./install.php') ? header('Location: install.php') : print('Missing Config File');
        exit;
    }

    include_once $configFile;
}

\Widget\Init::alloc();

\Typecho\Plugin::factory('index.php')->call('begin');

\Typecho\Router::dispatch();

\Typecho\Plugin::factory('index.php')->call('end');
