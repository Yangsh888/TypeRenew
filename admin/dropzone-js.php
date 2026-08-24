<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<script>
    (function () {
        var text = <?php echo \Typecho\Common::jsonEncode([
            'selected' => _t('已选择：%s%s'),
            'size' => _t('大小：%s，'),
            'replace' => _t('点击可重新选择，支持拖拽替换')
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT, '{}'); ?>;

        function format(template, values) {
            var index = 0;
            return String(template || '').replace(/%s/g, function () {
                return values[index++] || '';
            });
        }

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
                    if (title) title.textContent = format(text.selected, [name, extra]);
                    if (desc) desc.textContent = (size ? format(text.size, [size]) : '') + text.replace;
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
