<?php
define('__TYPECHO_ADMIN__', true);

if (!defined('__TYPECHO_ROOT_DIR__') && !@include_once __DIR__ . '/../config.inc.php') {
    file_exists(__DIR__ . '/../install.php') ? header('Location: ../install.php') : print('Missing Config File');
    exit;
}

\Widget\Init::alloc();
\Widget\Options::alloc()->to($options);

$options->response->redirect($options->adminUrl);
