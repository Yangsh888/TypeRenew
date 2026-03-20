<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<div style="text-align: center; padding: var(--sp-6) 0;">
    <h2 style="font-size: 4rem; margin: 0; font-weight: 300; color: var(--text-tertiary); line-height: 1;">404</h2>
    <p style="font-size: 1.1rem; color: var(--text-secondary); margin-bottom: var(--sp-4);"><?php _e('非常抱歉，您访问的页面不存在。'); ?></p>
    <form method="post" action="<?php $this->options->siteUrl(); ?>" role="search" style="display: flex; justify-content: center; gap: var(--sp-2);">
        <input type="search" name="s" class="search-input" placeholder="<?php _e('搜索一下试试？'); ?>" aria-label="<?php _e('搜索'); ?>" autofocus style="width: 200px; text-align: center; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.5rem; transition: all 0.2s ease; box-shadow: var(--control-shadow); font-size: 16px;" />
        <button type="submit" class="btn-submit"><?php _e('搜索'); ?></button>
    </form>
</div>

<?php $this->need('footer.php'); ?>
