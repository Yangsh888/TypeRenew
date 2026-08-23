<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<div class="error-page">
    <h2 class="error-code">404</h2>
    <p class="error-message"><?php _e('非常抱歉，您访问的页面不存在。'); ?></p>
    <form method="post" action="<?php $this->options->siteUrl(); ?>" role="search" class="search-form">
        <input type="search" name="s" class="search-input" placeholder="<?php _e('搜索一下试试？'); ?>" aria-label="<?php _e('搜索'); ?>" autofocus />
        <button type="submit" class="btn-submit"><?php _e('搜索'); ?></button>
    </form>
</div>

<?php $this->need('footer.php'); ?>
