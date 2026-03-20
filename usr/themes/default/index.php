<?php
/**
 * TypeRenew 官方默认模板
 *
 * @author TypeRenew Team
 * @version 6.0
 * @link https://github.com/TypeRenew
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<?php if (!($this->is('index')) && !($this->is('post'))): ?>
    <h3 class="archive-title" style="font-size: 1.2rem; margin-bottom: var(--sp-4); color: var(--text-secondary); font-weight: normal;"><?php $this->archiveTitle([
        'category' => _t('分类 %s 下的文章'),
        'search'   => _t('包含关键字 %s 的文章'),
        'tag'      => _t('标签 %s 下的文章'),
        'author'   => _t('%s 发布的文章')
    ], '', ''); ?></h3>
<?php endif; ?>

<?php if ($this->have()): ?>
    <?php while ($this->next()): ?>
        <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
            <?php postMeta($this, 'archive'); ?>
            <div class="post-content" itemprop="articleBody">
                <?php $this->content(_t('阅读全文')); ?>
            </div>
        </article>
        <?php if ($this->sequence < $this->length): ?>
            <hr class="post-separator">
        <?php endif; ?>
    <?php endwhile; ?>
<?php else: ?>
    <article class="post">
        <h2 class="post-title"><?php _e('没有找到内容'); ?></h2>
    </article>
<?php endif; ?>

<nav class="pagination-area" aria-label="<?php _e('分页导航'); ?>">
    <?php $this->pageNav(
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>',
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>',
        3,
        '...'
    ); ?>
</nav>

<?php $this->need('footer.php'); ?>
