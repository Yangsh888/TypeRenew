<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<script>
    (function () {
        function bindDropzones() {
            function formatSize(bytes) {
                if (!bytes || bytes <= 0) return '';
                var units = ['B', 'KB', 'MB', 'GB'];
                var size = bytes;
                var idx = 0;
                while (size >= 1024 && idx < units.length - 1) {
                    size = size / 1024;
                    idx++;
                }
                var fixed = idx === 0 ? 0 : (size >= 10 ? 1 : 2);
                return size.toFixed(fixed) + ' ' + units[idx];
            }

            function bindInput(input) {
                var dropzone = input.closest('.tr-dropzone');
                if (!dropzone) return;
                var title = dropzone.querySelector('.tr-dropzone-title');
                var desc = dropzone.querySelector('.tr-dropzone-desc');
                if (title && !title.dataset.trDefault) title.dataset.trDefault = title.textContent || '';
                if (desc && !desc.dataset.trDefault) desc.dataset.trDefault = desc.textContent || '';

                function render() {
                    var files = input.files;
                    if (!files || files.length === 0) {
                        dropzone.classList.remove('tr-dropzone-picked');
                        if (title && title.dataset.trDefault) title.textContent = title.dataset.trDefault;
                        if (desc && desc.dataset.trDefault) desc.textContent = desc.dataset.trDefault;
                        return;
                    }

                    var name = files[0].name || '';
                    var size = formatSize(files[0].size || 0);
                    var extra = files.length > 1 ? ('（+' + (files.length - 1) + '）') : '';
                    dropzone.classList.add('tr-dropzone-picked');
                    if (title) title.textContent = '已选择：' + name + extra;
                    if (desc) desc.textContent = (size ? ('大小：' + size + '，') : '') + '点击可重新选择，支持拖拽替换';
                }

                input.addEventListener('change', render, {passive: true});
                render();
            }

            document.querySelectorAll('.tr-dropzone-input[type="file"]').forEach(bindInput);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bindDropzones, {once: true});
        } else {
            bindDropzones();
        }
    })();
</script>
