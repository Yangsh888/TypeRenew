<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<?php if ($this->is('index') && $this->getTotal() > 0) : ?>
    <?php while ($this->next()) : ?>
        <article class="post" itemscope itemtype="https://schema.org/BlogPosting">
            <?php postMeta($this, 'archive'); ?>
            <div class="post-content" itemprop="articleBody">
                <?php $this->content(); ?>
            </div>
            <p class="post-footer-meta">
                <a class="read-more" href="<?php $this->permalink() ?>"><?php _e('阅读全文'); ?> →</a>
            </p>
        </article>
    <?php endwhile; ?>
    <?php $this->pageNav('«', '»', 1, '...', ['itemTag' => 'li', 'currentClass' => 'current']); ?>
<?php elseif ($this->is('index')) : ?>
    <div class="empty-state">
        <p class="empty-title"><?php _e('暂无文章'); ?></p>
        <p class="empty-hint"><?php _e('开始在后台发布你的第一篇文章吧。'); ?></p>
    </div>
<?php else :
    $this->need('page.php');
?>
<?php endif; ?>

<?php $this->need('footer.php'); ?>
