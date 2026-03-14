<?php
define('__TYPECHO_ADMIN__', true);

if (!defined('__TYPECHO_ROOT_DIR__') && !@include_once __DIR__ . '/../config.inc.php') {
    file_exists(__DIR__ . '/../install.php') ? header('Location: ../install.php') : print('Missing Config File');
    exit;
}

\Widget\Init::alloc();

\Typecho\Plugin::factory('admin/common.php')->call('begin');

$options = \Widget\Options::alloc();
$user = \Widget\User::alloc();
$security = \Widget\Security::alloc();
$menu = \Widget\Menu::alloc();

$request = $options->request;
$response = $options->response;

$currentMenu = $menu->getCurrentMenu();

if (!empty($currentMenu)) {
    $params = parse_url($currentMenu[2]);
    $adminFile = basename($params['path']);

    if ($user->pass('administrator', true)) {
        $mustUpgrade = version_compare(\Typecho\Common::VERSION, $options->version, '>');

        if ($mustUpgrade && 'upgrade.php' != $adminFile && 'backup.php' != $adminFile) {
            $response->redirect(\Typecho\Common::url('upgrade.php', $options->adminUrl));
        }
    }
}
