<?php
if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

$content = $write['content'];
$draftAction = $options->index . '/action/' . $write['draftAction'];
$draftToken = $security->getToken($draftAction);
?>
<div class="col-mb-12 col-tb-9" role="main">
    <?php if ($content->draft): ?>
        <?php if ($content->draft['cid'] != $content->cid): ?>
            <?php $contentModifyDate = new \Typecho\Date($content->draft['modified']); ?>
            <cite class="edit-draft-notice is-revision">
                <span class="edit-draft-notice-tag"><?php _e('修订版'); ?></span>
                <span class="edit-draft-notice-text"><?php _e('你正在编辑的是保存于 %s 的修订版，你也可以删除它', $contentModifyDate->word()); ?></span>
                <form action="<?php echo htmlspecialchars($draftAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="inline-operate-form">
                    <input type="hidden" name="_" value="<?php echo htmlspecialchars($draftToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="do" value="deleteDraft">
                    <input type="hidden" name="cid" value="<?php echo (int) $content->cid; ?>">
                    <button type="submit" class="btn btn-link"><?php _e('删除它'); ?></button>
                </form>
            </cite>
        <?php else: ?>
            <cite class="edit-draft-notice is-draft">
                <span class="edit-draft-notice-tag"><?php _e('草稿'); ?></span>
                <span class="edit-draft-notice-text"><?php _e('当前正在编辑的是未发布的草稿'); ?></span>
            </cite>
        <?php endif; ?>
        <input name="draft" type="hidden" value="<?php echo $content->draft['cid']; ?>"/>
    <?php endif; ?>

    <div class="tr-editor-card">
        <p class="title">
            <label for="title" class="sr-only"><?php _e('标题'); ?></label>
            <input type="text" id="title" name="title" autocomplete="off" value="<?php $content->title(); ?>"
                   placeholder="<?php _e('标题'); ?>" class="w-100 text title"/>
        </p>
        <p class="mono url-slug">
            <label for="slug" class="sr-only"><?php _e('网址缩略名'); ?></label>
            <?php echo $write['permalink']; ?>
        </p>
        <?php if ($content->have() && method_exists($content, 'isFuturePublish') && $content->isFuturePublish()): ?>
            <?php $previewCid = !empty($content->draft['cid']) ? (int) $content->draft['cid'] : (int) $content->cid; ?>
            <cite class="edit-draft-notice is-schedule">
                <span class="edit-draft-notice-tag"><?php _e('定时发布'); ?></span>
                <span class="edit-draft-notice-text"><?php _e('当前内容将于 %s 定时发布，前台链接届时才可访问。', $content->date('Y-m-d H:i')); ?></span>
                <?php if ($previewCid > 0): ?>
                    <a href="<?php $options->adminUrl('preview.php?cid=' . $previewCid); ?>" target="_blank" rel="noopener noreferrer"><?php _e('立即预览'); ?></a>
                <?php endif; ?>
            </cite>
        <?php endif; ?>
        <p>
            <label for="text" class="sr-only"><?php echo htmlspecialchars((string) $write['textLabel'], ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea style="--tr-editor-h: <?php $options->editorSize(); ?>px" autocomplete="off" id="text"
                      name="text" class="w-100 mono tr-editor"><?php echo htmlspecialchars((string) $content->text); ?></textarea>
        </p>

        <?php include 'custom-fields.php'; ?>
    </div>

    <p class="submit">
        <span class="left">
            <button type="button" id="btn-cancel-preview" class="btn"><i class="i-caret-left"></i> <?php _e('取消预览'); ?></button>
        </span>
        <span class="right">
            <input type="hidden" name="do" value="publish" />
            <input type="hidden" name="cid" value="<?php $content->cid(); ?>"/>
            <button type="button" id="btn-preview" class="btn"><i class="i-exlink"></i> <?php echo htmlspecialchars((string) $write['previewLabel'], ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="submit" name="do" value="save" id="btn-save" class="btn"><?php _e('保存草稿'); ?></button>
            <button type="submit" name="do" value="publish" class="btn primary" id="btn-submit"><?php echo htmlspecialchars((string) $write['publishLabel'], ENT_QUOTES, 'UTF-8'); ?></button>
            <?php if (
                $content->isMarkdown
                || ($options->markdown && !$content->have())
                || (class_exists('VditorRenew_Plugin') && !empty(\VditorRenew_Plugin::getSettings()['enabled']))
            ): ?>
                <input type="hidden" name="markdown" value="1"/>
            <?php endif; ?>
        </span>
    </p>

    <?php \Typecho\Plugin::factory('admin/' . $write['hook'])->call('content', $content); ?>
</div>
