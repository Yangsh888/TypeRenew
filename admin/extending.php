<?php

include 'common.php';

$panel = $request->get('panel');
$panelTable = $options->panelTable;

if (!isset($panelTable['file']) || !in_array(urlencode($panel), $panelTable['file'])) {
    throw new \Typecho\Plugin\Exception(_t('页面不存在'), 404);
}

[$pluginName, $file] = explode('/', trim($panel, '/'), 2);

$panelFile = $options->pluginDir($pluginName) . '/' . $file;

ob_start();
require_once $panelFile;
$panelOutput = (string) ob_get_clean();

$isFullHtml = preg_match('/<\s*!doctype\s+html|<\s*html\b/i', $panelOutput) === 1;
if ($isFullHtml) {
    echo $panelOutput;
    return;
}

include 'header.php';
include 'menu.php';
?>

<main class="main">
    <div class="body container">
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 tr-panel">
                <?php echo $panelOutput; ?>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
include 'footer.php';
