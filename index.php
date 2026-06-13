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

// 邮件队列自愈兜底: 注册一个低频后台投递器, 在响应结束后顺手消费积压邮件。
// 必须在 dispatch 前注册, 因为响应在控制器内 respond() 时即消费后台任务。
\Typecho\Mail\Queue::maybeDrain(\Typecho\Db::get(), \Widget\Options::alloc());

\Typecho\Router::dispatch();

\Typecho\Plugin::factory('index.php')->call('end');
