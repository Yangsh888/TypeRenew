<?php
if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}
?>
<div id="edit-secondary" class="col-mb-12 col-tb-3" role="complementary" aria-label="<?php _e('写作设置'); ?>">
    <div class="tr-side-stack">
        <div class="tr-write-side-head">
            <span class="tr-write-side-title"><?php _e('写作设置'); ?></span>
            <button type="button" id="btn-side-close" class="btn btn-xs tr-write-side-close"><?php _e('收起'); ?></button>
        </div>
        <ul class="typecho-option-tabs">
            <li class="active w-50"><a href="#tab-advance"><?php _e('选项'); ?></a></li>
            <li class="w-50"><a href="#tab-files" id="tab-files-btn"><?php _e('附件'); ?></a></li>
        </ul>

        <div id="tab-advance" class="tab-content">
            <?php echo $writeSide['advance']; ?>
        </div>

        <div id="tab-files" class="tab-content" hidden>
            <?php include 'file-upload.php'; ?>
        </div>
    </div>
</div>
