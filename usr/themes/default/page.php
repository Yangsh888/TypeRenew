<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<?php renderSingle($this, 'page'); ?>

<?php $this->need('comments.php'); ?>

<?php $this->need('footer.php'); ?>
