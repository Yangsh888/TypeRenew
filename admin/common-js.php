<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<script src="<?php $options->adminStaticUrl('js', 'jquery.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'jquery-ui.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'typecho.js'); ?>"></script>
<script>
    (function () {
        $(document).ready(function() {
            (function () {
                var prefix = '<?php echo \Typecho\Cookie::getPrefix(); ?>',
                    cookies = {
                        notice      :   $.cookie(prefix + '__typecho_notice'),
                        noticeType  :   $.cookie(prefix + '__typecho_notice_type'),
                        highlight   :   $.cookie(prefix + '__typecho_notice_highlight')
                    },
                    path = '<?php echo \Typecho\Cookie::getPath(); ?>',
                    domain = '<?php echo \Typecho\Cookie::getDomain(); ?>',
                    secure = <?php echo json_encode(\Typecho\Cookie::getSecure()); ?>;

                if (!!cookies.notice && 'success|notice|error'.indexOf(cookies.noticeType) >= 0) {
                    var messages = $.parseJSON(cookies.notice),
                        isTrAdmin = document.body && (' ' + document.body.className + ' ').indexOf(' tr-admin ') >= 0;
                    var sanitizeMessage = function (raw) {
                        var wrap = document.createElement('div');
                        wrap.innerHTML = String(raw == null ? '' : raw);
                        var nodes = Array.prototype.slice.call(wrap.querySelectorAll('*'));
                        nodes.forEach(function (node) {
                            if (node.tagName !== 'A') {
                                var textNode = document.createTextNode(node.textContent || '');
                                node.parentNode.replaceChild(textNode, node);
                                return;
                            }
                            var href = (node.getAttribute('href') || '').trim();
                            if (!href || /^(javascript|data|vbscript):/i.test(href)) {
                                var plain = document.createTextNode(node.textContent || '');
                                node.parentNode.replaceChild(plain, node);
                                return;
                            }
                            if (!/^(https?:\/\/|\/|#)/i.test(href)) {
                                href = 'https://' + href.replace(/^\/+/, '');
                            }
                            node.setAttribute('href', href);
                            node.setAttribute('target', '_blank');
                            node.setAttribute('rel', 'noopener noreferrer nofollow');
                            var attrs = Array.prototype.slice.call(node.attributes || []);
                            attrs.forEach(function (attr) {
                                if (['href', 'target', 'rel'].indexOf(attr.name) < 0) {
                                    node.removeAttribute(attr.name);
                                }
                            });
                        });
                        return wrap.innerHTML;
                    };
                    var normalized = (Array.isArray(messages) ? messages : [messages]).map(sanitizeMessage);

                    if (isTrAdmin) {
                        var payload = {
                            type: cookies.noticeType,
                            messages: normalized,
                            highlight: cookies.highlight || null
                        };
                        window.__trNotice = payload;
                        if (window.TypechoNotice && typeof window.TypechoNotice.show === 'function') {
                            window.TypechoNotice.show(payload.type || 'notice', payload.messages, {allowHtml: true});
                            if (payload.highlight && typeof window.TypechoNotice.highlight === 'function') {
                                window.TypechoNotice.highlight(payload.highlight);
                                $.cookie(prefix + '__typecho_notice_highlight', null, {path : path, domain: domain, secure: secure});
                                cookies.highlight = null;
                            }
                            window.__trNotice = null;
                        }
                    } else {
                        var head = $('.typecho-head-nav'),
                            p = $('<div class="message popup ' + cookies.noticeType + '">'
                            + '<ul><li>' + normalized.join('</li><li>')
                            + '</li></ul></div>'), offset = 0;

                        if (head.length > 0) {
                            p.insertAfter(head);
                        } else {
                            p.prependTo(document.body);
                        }

                        p.slideDown(function () {
                            var t = $(this), color = '#C6D880';

                            if (t.hasClass('error')) {
                                color = '#FBC2C4';
                            } else if (t.hasClass('notice')) {
                                color = '#FFD324';
                            }

                            t.effect('highlight', {color : color})
                                .delay(5000).fadeOut(function () {
                                $(this).remove();
                            });
                        });
                    }

                    $.cookie(prefix + '__typecho_notice', null, {path : path, domain: domain, secure: secure});
                    $.cookie(prefix + '__typecho_notice_type', null, {path : path, domain: domain, secure: secure});
                }

                if (cookies.highlight) {
                    var isTrAdmin2 = document.body && (' ' + document.body.className + ' ').indexOf(' tr-admin ') >= 0;
                    if (isTrAdmin2) {
                        if (!window.__trNotice || typeof window.__trNotice !== 'object') {
                            window.__trNotice = {};
                        }
                        if (!window.__trNotice.highlight) {
                            window.__trNotice.highlight = cookies.highlight;
                        }
                        if (window.TypechoNotice && typeof window.TypechoNotice.highlight === 'function') {
                            window.TypechoNotice.highlight(cookies.highlight);
                            window.__trNotice.highlight = null;
                        }
                    } else {
                        $('#' + cookies.highlight).effect('highlight', 1000);
                    }
                    $.cookie(prefix + '__typecho_notice_highlight', null, {path : path, domain: domain, secure: secure});
                }
            })();

            if ($('.typecho-login').length == 0) {
                $('a').each(function () {
                    var t = $(this), href = t.attr('href');

                    if ((href && href[0] == '#')
                        || /^<?php echo preg_quote($options->adminUrl, '/'); ?>.*$/.exec(href) 
                            || /^<?php echo substr(preg_quote(\Typecho\Common::url('s', $options->index), '/'), 0, -1); ?>action\/[_a-zA-Z0-9\/]+.*$/.exec(href)) {
                        return;
                    }

                    t.attr('target', '_blank')
                        .attr('rel', 'noopener noreferrer');
                });
            }

            (function () {
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
            })();
        });
    })();
</script>
