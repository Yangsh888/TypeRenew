<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php
$trNotice = null;
$trNoticeType = null;
$trHighlight = null;
if (isset($_COOKIE[session_name()]) && \Utils\Session::start()) {
    if (isset($_SESSION['__typecho_notice'])) {
        $trNotice = $_SESSION['__typecho_notice'];
        unset($_SESSION['__typecho_notice']);
    }
    if (isset($_SESSION['__typecho_notice_type'])) {
        $trNoticeType = $_SESSION['__typecho_notice_type'];
        unset($_SESSION['__typecho_notice_type']);
    }
    if (isset($_SESSION['__typecho_notice_highlight'])) {
        $trHighlight = $_SESSION['__typecho_notice_highlight'];
        unset($_SESSION['__typecho_notice_highlight']);
    }
}
?>
<script>
    $(document).ready(function() {
            var messages = <?php echo json_encode($trNotice, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                noticeType = <?php echo json_encode($trNoticeType, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                highlight = <?php echo json_encode($trHighlight, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            var isTrAdmin = document.body && (' ' + document.body.className + ' ').indexOf(' tr-admin ') >= 0;

            if (messages && 'success|notice|error'.indexOf(noticeType) >= 0) {
                var sanitizeMessage = function (raw) {
                    var wrap = document.createElement('div');
                    wrap.innerHTML = String(raw == null ? '' : raw);
                    var nodes = Array.prototype.slice.call(wrap.querySelectorAll('*'));
                    nodes.forEach(function (node) {
                        if (!node.parentNode) {
                            return;
                        }
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

                if (isTrAdmin && window.TypechoNotice && typeof window.TypechoNotice.show === 'function') {
                    window.TypechoNotice.show(noticeType || 'notice', normalized, {allowHtml: true});
                } else {
                    var popup = $('<div class="message popup ' + noticeType + '">'
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
                }
            }

            if (highlight) {
                if (isTrAdmin && window.TypechoNotice && typeof window.TypechoNotice.highlight === 'function') {
                    window.TypechoNotice.highlight(highlight);
                } else {
                    $('#' + highlight).effect('highlight', 1000);
                }
            }
    });
</script>
