<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php
$trSessionNotice = null;
$trSessionNoticeType = null;
$trSessionHighlight = null;
if (session_status() === PHP_SESSION_ACTIVE) {
    if (isset($_SESSION['__typecho_notice'])) {
        $trSessionNotice = $_SESSION['__typecho_notice'];
        unset($_SESSION['__typecho_notice']);
    }
    if (isset($_SESSION['__typecho_notice_type'])) {
        $trSessionNoticeType = $_SESSION['__typecho_notice_type'];
        unset($_SESSION['__typecho_notice_type']);
    }
    if (isset($_SESSION['__typecho_notice_highlight'])) {
        $trSessionHighlight = $_SESSION['__typecho_notice_highlight'];
        unset($_SESSION['__typecho_notice_highlight']);
    }
}
?>
<script>
    $(document).ready(function() {
            var sessionNotice = <?php echo json_encode($trSessionNotice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                sessionNoticeType = <?php echo json_encode($trSessionNoticeType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                sessionHighlight = <?php echo json_encode($trSessionHighlight, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                prefix = '<?php echo \Typecho\Cookie::getPrefix(); ?>',
                cookies = {
                    notice      :   $.cookie(prefix + '__typecho_notice'),
                    noticeType  :   $.cookie(prefix + '__typecho_notice_type'),
                    highlight   :   $.cookie(prefix + '__typecho_notice_highlight')
                },
                path = '<?php echo \Typecho\Cookie::getPath(); ?>',
                domain = '<?php echo \Typecho\Cookie::getDomain(); ?>',
                secure = <?php echo json_encode(\Typecho\Cookie::getSecure()); ?>;

            if (!cookies.notice && sessionNotice) {
                cookies.notice = JSON.stringify(sessionNotice);
                cookies.noticeType = sessionNoticeType || 'notice';
            }
            if (!cookies.highlight && sessionHighlight) {
                cookies.highlight = sessionHighlight;
            }

            if (!!cookies.notice && 'success|notice|error'.indexOf(cookies.noticeType) >= 0) {
                var messages = [];
                try {
                    messages = $.parseJSON(cookies.notice);
                } catch (e) {
                    messages = [cookies.notice];
                }
                var isTrAdmin = document.body && (' ' + document.body.className + ' ').indexOf(' tr-admin ') >= 0;
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
                var renderLegacyPopup = function () {
                    var popup = $('<div class="message popup ' + cookies.noticeType + '">'
                        + '<ul><li>' + normalized.join('</li><li>')
                        + '</li></ul></div>');
                    popup.prependTo(document.body);
                    popup.slideDown(function () {
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
                };

                if (isTrAdmin) {
                    if (window.TypechoNotice && typeof window.TypechoNotice.show === 'function') {
                        window.TypechoNotice.show(cookies.noticeType || 'notice', normalized, {allowHtml: true});
                        if (cookies.highlight && typeof window.TypechoNotice.highlight === 'function') {
                            window.TypechoNotice.highlight(cookies.highlight);
                            $.cookie(prefix + '__typecho_notice_highlight', null, {path : path, domain: domain, secure: secure});
                            cookies.highlight = null;
                        }
                    } else {
                        renderLegacyPopup();
                    }
                } else {
                    renderLegacyPopup();
                }

                $.cookie(prefix + '__typecho_notice', null, {path : path, domain: domain, secure: secure});
                $.cookie(prefix + '__typecho_notice_type', null, {path : path, domain: domain, secure: secure});
            }

            if (cookies.highlight) {
                if (isTrAdmin) {
                    if (window.TypechoNotice && typeof window.TypechoNotice.highlight === 'function') {
                        window.TypechoNotice.highlight(cookies.highlight);
                    } else {
                        $('#' + cookies.highlight).effect('highlight', 1000);
                    }
                } else {
                    $('#' + cookies.highlight).effect('highlight', 1000);
                }
                $.cookie(prefix + '__typecho_notice_highlight', null, {path : path, domain: domain, secure: secure});
            }
    });
</script>
