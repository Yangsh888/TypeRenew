<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<script src="<?php $options->adminStaticUrl('js', 'purify.js'); ?>"></script>
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
            selectAllEl :   '.typecho-table-select-all',
            actionEl    :   '.dropdown-menu a,button.btn-operate'
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
