<?php
if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

$bodyClass = $bodyClass ?? '';
$bodyClass = trim((string) $bodyClass);
$isBody100 = strpos(' ' . $bodyClass . ' ', ' body-100 ') !== false;
$trAdminEnabled = !$isBody100;
if (!$isBody100) {
    $bodyClass = trim($bodyClass . ' tr-admin');
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $page = pathinfo($script, PATHINFO_FILENAME);
    $page = preg_replace('/[^a-z0-9]+/i', '-', (string) $page);
    $page = strtolower(trim((string) $page, '-'));
    if ($page !== '') {
        $bodyClass = trim($bodyClass . ' tr-page-' . $page);
    }
}

$header = '';
if (!empty($trAdminEnabled)) {
    $header .= '<script>(function(){const key="trTheme";let preference="system";try{preference=localStorage.getItem(key)||"system";}catch(e){}let dark=false;if(preference==="dark"){dark=true;}else if(preference==="system"){try{dark=window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches;}catch(e){}}const root=document.documentElement;root.classList.toggle("tr-theme-dark",dark);})();</script>';
}
$header .= '<link rel="stylesheet" href="' . $options->adminStaticUrl('css', 'normalize.css', true) . '">
<link rel="stylesheet" href="' . $options->adminStaticUrl('css', 'grid.css', true) . '">
<link rel="stylesheet" href="' . $options->adminStaticUrl('css', 'style.css', true) . '">
<link rel="stylesheet" href="' . $options->adminStaticUrl('css', 'renew-ui.css', true) . '">';

$header = \Typecho\Plugin::factory('admin/header.php')->filter('header', $header);

?><!DOCTYPE HTML>
<html>
    <head>
        <meta charset="<?php $options->charset(); ?>">
        <meta name="renderer" content="webkit">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <title><?php _e('%s - %s - Powered by TypeRenew', $menu->title, $options->title); ?></title>
        <meta name="robots" content="noindex, nofollow">
        <?php echo $header; ?>
    </head>
    <body<?php if ($bodyClass !== '') {echo ' class="' . $bodyClass . '"';} ?>>
