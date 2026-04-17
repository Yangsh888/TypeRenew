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
    (function () {
        function cancelPreview() {
            if (window.parent) {
                window.parent.postMessage('cancelPreview', '*');
            }
        }

        function ensureCloseButton() {
            if (!document.body || document.getElementById('tr-preview-close')) {
                return;
            }

            var button = document.createElement('button');
            button.type = 'button';
            button.id = 'tr-preview-close';
            button.textContent = '关闭预览';
            button.setAttribute('aria-label', '关闭预览');
            button.style.position = 'fixed';
            button.style.top = '16px';
            button.style.right = '16px';
            button.style.zIndex = '2147483647';
            button.style.padding = '10px 14px';
            button.style.border = '1px solid rgba(255,255,255,.16)';
            button.style.borderRadius = '10px';
            button.style.background = 'rgba(17,24,39,.92)';
            button.style.color = '#fff';
            button.style.fontSize = '14px';
            button.style.lineHeight = '1';
            button.style.cursor = 'pointer';
            button.style.boxShadow = '0 10px 30px rgba(0,0,0,.22)';
            button.addEventListener('click', cancelPreview);
            document.body.appendChild(button);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ensureCloseButton);
        } else {
            ensureCloseButton();
        }

        window.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                cancelPreview();
            }
        });

        window.addEventListener('beforeunload', cancelPreview);
    })();
</script>
