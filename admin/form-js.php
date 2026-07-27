<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<script>
(function () {
    $(document).ready(function () {
        const error = $('.typecho-option .error:first');

        if (error.length > 0) {
            $('html,body').scrollTop(error.parents('.typecho-option').offset().top);
        }

        $('.main form').submit(function (event) {
            const self = $(this);
            const submitter = event.originalEvent && event.originalEvent.submitter;
            const tip = self.hasClass('inline-operate-form')
                ? $(submitter || $('button[type=submit]', this).get(0)).attr('lang')
                : null;

            if (tip && !confirm(tip)) {
                return false;
            }

            if (self.hasClass('submitting')) {
                return false;
            } else {
                $('button[type=submit]', this).attr('disabled', 'disabled');
                self.addClass('submitting');
            }
        }).on('submitted', function () {
            $('button[type=submit]', this).removeAttr('disabled');
            $(this).removeClass('submitting');
        });

        $('label input[type=text]').click(function (e) {
            const check = $('#' + $(this).parents('label').attr('for'));
            check.prop('checked', true);
            return false;
        });

        $('.main form input[type="url"]').each(function () {
            const self = $(this);
            const input = $('<input type="hidden" />').attr('name', self.attr('name'));

            function setInput() {
                const url = $.trim(self.val());

                if (!url) {
                    input.val('');
                    return;
                }

                try {
                    const urlObj = new URL(url);
                    input.val(urlObj.toString());
                } catch {
                    input.val(url);
                }
            }

            self.removeAttr('name').after(input).on('input', setInput);
            setInput();
        });
    });
})();
</script>
