<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<script>
(function () {
    $(document).ready(function () {
        var closeAllDropdown = function () {
            $('.btn-drop .dropdown-toggle.active').removeClass('active');
            $('.btn-drop .dropdown-menu:visible').hide();
        };

        $('.typecho-list-table').tableSelectable({
            checkEl     :   'input[type=checkbox]',
            rowEl       :   'tr',
            selectAllEl :   '.typecho-table-select-all'
        });

        $('.typecho-list-operate').on('click', '.dropdown-menu a, button.btn-operate', function (e) {
            var trigger = $(this);
            var form = trigger.closest('.typecho-list-operate').siblings('form.operate-form').first();
            var href = trigger.attr('href');
            var confirmText = trigger.attr('lang');
            var parts;

            if (!href || !form.length) {
                return;
            }

            e.preventDefault();

            if (confirmText && !confirm(confirmText)) {
                return;
            }

            parts = href.split('?');

            form.find('input.tr-operate-param').remove();
            form.attr('action', parts[0]);

            $.each((parts[1] || '').split('&'), function (_, pair) {
                var index;
                var name;
                var value;

                if (!pair) {
                    return;
                }

                index = pair.indexOf('=');
                name = decodeURIComponent((index >= 0 ? pair.slice(0, index) : pair).replace(/\+/g, ' '));
                value = decodeURIComponent((index >= 0 ? pair.slice(index + 1) : '').replace(/\+/g, ' '));

                if (!name) {
                    return;
                }

                $('<input type="hidden" class="tr-operate-param">')
                    .attr('name', name)
                    .val(value)
                    .appendTo(form);
            });

            form.trigger('submit');
        });

        $('.btn-drop').dropdownMenu({
            btnEl       :   '.dropdown-toggle',
            menuEl      :   '.dropdown-menu'
        });

        $(document).on('click', function (e) {
            if ($(e.target).closest('.btn-drop').length === 0) {
                closeAllDropdown();
            }
        });

        $(document).on('keydown', function (e) {
            if ((e.key && e.key === 'Escape') || e.keyCode === 27) {
                closeAllDropdown();
            }
        });

        $('.btn-drop .dropdown-toggle').on('click', function () {
            var current = $(this);
            $('.btn-drop .dropdown-toggle.active').not(current).removeClass('active');
            $('.btn-drop .dropdown-menu:visible').not(current.closest('.btn-drop').find('.dropdown-menu')).hide();
        });
    });
})();
</script>
