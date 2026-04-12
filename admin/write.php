<?php
if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

if (!function_exists('tr_write_markdown')) {
    function tr_write_markdown($content, $options): bool
    {
        return $content->isMarkdown
            || ($options->markdown && !$content->have())
            || (class_exists('VditorRenew_Plugin') && !empty(\VditorRenew_Plugin::getSettings()['enabled']));
    }
}

$content = $write['content'];
?>
<div class="col-mb-12 col-tb-9" role="main">
    <?php if ($content->draft): ?>
        <?php if ($content->draft['cid'] != $content->cid): ?>
            <?php $contentModifyDate = new \Typecho\Date($content->draft['modified']); ?>
            <cite class="edit-draft-notice"><?php _e(
                '你正在编辑的是保存于 %s 的修订版, 你也可以 <a href="%s">删除它</a>',
                $contentModifyDate->word(),
                $security->getIndex('/action/' . $write['draftAction'] . '?do=deleteDraft&cid=' . $content->cid)
            ); ?></cite>
        <?php else: ?>
            <cite class="edit-draft-notice"><?php _e('当前正在编辑的是未发布的草稿'); ?></cite>
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
            <?php if (tr_write_markdown($content, $options)): ?>
                <input type="hidden" name="markdown" value="1"/>
            <?php endif; ?>
        </span>
    </p>

    <?php \Typecho\Plugin::factory('admin/' . $write['hook'])->call('content', $content); ?>
</div>
