<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<?php renderSingle($this, 'post'); ?>

<nav aria-label="<?php _e('文章导航'); ?>">
    <ul class="post-near">
        <li><strong><?php _e('上一篇'); ?></strong> <?php $this->thePrev('%s', _t('没有了')); ?></li>
        <li><strong><?php _e('下一篇'); ?></strong> <?php $this->theNext('%s', _t('没有了')); ?></li>
    </ul>
</nav>

<?php $this->need('comments.php'); ?>

<?php $this->need('footer.php'); ?>
