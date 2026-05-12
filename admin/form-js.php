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
            }

            $('button[type=submit]', this).attr('disabled', 'disabled');
            self.addClass('submitting');
        }).on('submitted', function () {
            $('button[type=submit]', this).removeAttr('disabled');
            $(this).removeClass('submitting');
        });

        $('label input[type=text]').on('focus click', function () {
            const id = $(this).closest('label').attr('for');
            if (id) {
                $('#' + id).prop('checked', true);
            }
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
                    input.val(url);
                }
            }

            self.removeAttr('name')
                .after(input)
                .on('input change blur', setInput)
                .closest('form')
                .on('submit', setInput);
            setInput();
        });

        function bindPlaceholderPassword(inputName, changedId) {
            const passInput = document.querySelector('input[name="' + inputName + '"]');
            const changedInput = document.getElementById(changedId);
            if (!passInput || !changedInput) {
                return;
            }

            function isPlaceholder() {
                return passInput.value === '********';
            }

            function markChanged() {
                changedInput.value = '1';
            }

            function clearPlaceholder() {
                if (!isPlaceholder()) {
                    return;
                }

                passInput.value = '';
                markChanged();
            }

            passInput.addEventListener('input', markChanged);
            passInput.addEventListener('change', markChanged);
            passInput.addEventListener('focus', function () {
                if (isPlaceholder()) {
                    this.select();
                }
            });
            passInput.addEventListener('keydown', function (event) {
                if (
                    isPlaceholder()
                    && event.key.length === 1
                ) {
                    clearPlaceholder();
                }
            });
            passInput.addEventListener('paste', clearPlaceholder);
            passInput.form && passInput.form.addEventListener('submit', function () {
                if (passInput.value !== '********') {
                    markChanged();
                }
            });
        }

        bindPlaceholderPassword('mailSmtpPass', 'mailSmtpPassChanged');
        bindPlaceholderPassword('cacheRedisPassword', 'cacheRedisPasswordChanged');
    });
})();
</script>
