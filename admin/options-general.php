<?php
include 'common.php';
include 'header.php';
include 'menu.php';
?>

<main class="main">
    <div class="body container">
        <div class="row typecho-page-main" role="form">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2 tr-panel">
                <?php include 'options-tabs.php'; ?>
                <div class="tr-settings-body">
                <?php \Widget\Options\General::alloc()->form()->render(); ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
?>
<script>
(function () {
    'use strict';
    function bindCustom(selectName, inputName, customValue) {
        var select = document.querySelector('select[name=' + selectName + ']');
        var input = document.querySelector('input[name=' + inputName + ']');
        if (!select || !input) return;

        var option = input.closest('.typecho-option');
        if (!option) return;

        function toggle() {
            option.style.display = select.value === customValue ? '' : 'none';
        }

        toggle();
        select.addEventListener('change', toggle);
    }

    bindCustom('ipSource', 'ipSourceCustom', 'custom');
    bindCustom('githubRawMirror', 'githubRawMirrorCustom', '_');
})();
</script>
<?php
include 'footer.php';
?>
