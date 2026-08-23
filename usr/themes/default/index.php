<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<?php if ($this->is('archive')) : ?>
    <h1 class="archive-title"><?php $this->archiveTitle([
        'category' => _t('分类 %s 下的文章'),
        'search'   => _t('包含关键字 %s 的文章'),
        'tag'      => _t('标签 %s 下的文章'),
        'author'   => _t('%s 发布的文章')
    ], '', ''); ?></h1>
<?php endif; ?>

<?php if ($this->getTotal() > 0) : ?>
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
<?php else : ?>
    <div class="empty-state">
        <p class="empty-title"><?php _e('暂无文章'); ?></p>
        <p class="empty-hint"><?php $this->is('archive')
            ? _e('换一个条件试试，或返回首页浏览全部内容。')
            : _e('开始在后台发布你的第一篇文章吧。'); ?></p>
    </div>
<?php endif; ?>

<?php $this->need('footer.php'); ?>
