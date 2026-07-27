<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<script>
(function () {
    $(document).ready(function () {
        const error = $('.typecho-option .error:first');

        if (error.length > 0) {
            $('html,body').scrollTop(error.parents('.typecho-option').offset().top);
        }

        $('.main form').submit(function () {
            const self = $(this);

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

        // 行内操作表单(停用插件/删除草稿/切换外观)的二次确认
        // 提示语写在提交按钮的 lang 属性上, 没有该属性则不拦
        $('.inline-operate-form').submit(function () {
            const tip = $('button[type=submit]', this).attr('lang');
            return !tip || confirm(tip);
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
                const url = self.val();

                try {
                    const urlObj = new URL(url);
                    input.val(urlObj.toString());
                } catch {
                }
            }

            self.removeAttr('name').after(input).on('input', setInput);
            setInput();
        });
    });
})();
</script>
