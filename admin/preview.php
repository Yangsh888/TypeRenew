<?php

include 'common.php';

\Widget\Archive::alloc('type=single&checkPermalink=0&preview=1')->to($content);

if (!$content->have()) {
    $response->redirect($options->adminUrl);
}

if (!$user->pass('editor', true) && $content->authorId != $user->uid) {
    $response->redirect($options->adminUrl);
}

$content->render();
?>
<script>
    window.onbeforeunload = function () {
        if (!!window.parent) {
            window.parent.postMessage('cancelPreview', '<?php $options->rootUrl(); ?>');
        }
    }
</script>
