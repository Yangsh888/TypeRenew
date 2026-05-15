<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<script>
    (function () {
        $(document).ready(function () {
            if ($('.typecho-login').length > 0) {
                return;
            }

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
        });
    })();
</script>
