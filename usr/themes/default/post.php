<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<div class="col-mb-12 col-8" id="main" role="main">
    <?php renderSingle($this, 'post'); ?>

    <?php $this->need('comments.php'); ?>

    <ul class="post-near">
        <li>上一篇: <?php $this->thePrev('%s', _t('没有了')); ?></li>
        <li>下一篇: <?php $this->theNext('%s', _t('没有了')); ?></li>
    </ul>
</div>

<?php $this->need('sidebar.php'); ?>
<?php $this->need('footer.php'); ?>
