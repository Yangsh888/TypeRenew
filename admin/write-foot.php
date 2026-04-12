<?php
if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

$content = $write['content'];
$hook = 'admin/' . $write['hook'];

include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
include 'write-js.php';

\Typecho\Plugin::factory($hook)->trigger($plugged)->call('richEditor', $content);
if (!$plugged) {
    include 'editor-js.php';
}

include 'file-upload-js.php';
include 'custom-fields-js.php';
\Typecho\Plugin::factory($hook)->call('bottom', $content);
include 'footer.php';
