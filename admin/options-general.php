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
$(function () {
    var select = $('select[name=ipSource]'),
        custom = $('input[name=ipSourceCustom]').closest('.typecho-option');

    function toggleCustom() {
        select.val() === 'custom' ? custom.show() : custom.hide();
    }

    if (select.length > 0) {
        toggleCustom();
        select.on('change', toggleCustom);
    }
});
</script>
<?php
include 'footer.php';
?>
