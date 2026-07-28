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
    var select = document.querySelector('select[name=ipSource]');
    if (!select) return;

    var customInput = document.querySelector('input[name=ipSourceCustom]');
    if (!customInput) return;

    var customOption = customInput.closest('.typecho-option');
    if (!customOption) return;

    function toggleCustom() {
        customOption.style.display = select.value === 'custom' ? '' : 'none';
    }

    toggleCustom();
    select.addEventListener('change', toggleCustom);
})();
</script>
<?php
include 'footer.php';
?>
