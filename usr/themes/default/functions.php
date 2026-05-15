<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeConfig($form)
{
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('站点 LOGO 地址'),
        _t('在这里填入一个图片 URL 地址, 以在网站标题前加上一个 LOGO')
    );
    $form->addInput($logoUrl->addRule('url', _t('请填写一个合法的URL地址')));

    $colorSchema = new \Typecho\Widget\Helper\Form\Element\Select(
        'colorSchema',
        array(
            'auto' => _t('自动 (跟随系统)'),
            'light' => _t('浅色'),
            'dark' => _t('深色')
        ),
        'auto',
        _t('外观风格'),
        _t('选择主题的颜色风格')
    );
    $form->addInput($colorSchema);
}

function postMeta(\Widget\Archive $archive, string $metaType = 'archive')
{
    $titleTag = $metaType === 'archive' ? 'h2' : 'h1';
?>
    <<?php echo $titleTag ?> class="post-title" itemprop="name headline">
        <a itemprop="url" href="<?php $archive->permalink() ?>"><?php $archive->title() ?></a>
    </<?php echo $titleTag ?>>
    
    <?php if ($metaType !== 'page'): ?>
        <ul class="post-meta">
            <li itemprop="author" itemscope itemtype="http://schema.org/Person">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <a itemprop="name" href="<?php $archive->author->permalink(); ?>" rel="author"><?php $archive->author(); ?></a>
            </li>
            <li>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <time datetime="<?php $archive->date('c'); ?>" itemprop="datePublished"><?php $archive->date(); ?></time>
            </li>
            <li>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                <?php $archive->category(','); ?>
            </li>
            <?php if ($metaType === 'archive'): ?>
                <li itemprop="interactionCount">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    <a itemprop="discussionUrl" href="<?php $archive->permalink() ?>#comments"><?php $archive->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?></a>
                </li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
<?php
}

function renderSingle(\Widget\Archive $archive, string $metaType = 'post'): void
{
?>
    <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
        <?php postMeta($archive, $metaType); ?>
        <div class="post-content" itemprop="articleBody">
            <?php $archive->content(); ?>
        </div>
        <?php if ($metaType === 'post' && count($archive->tags) > 0): ?>
            <p itemprop="keywords" class="tags">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                <?php $archive->tags(', ', true, 'none'); ?>
            </p>
        <?php endif; ?>
    </article>
<?php
}

function threadedComments($comments, $options)
{
    $commentClass = '';
    if ($comments->authorId) {
        if ($comments->authorId == $comments->ownerId) {
            $commentClass .= ' comment-by-author';
        } else {
            $commentClass .= ' comment-by-user';
        }
    }
?>
    <li id="li-<?php $comments->theId(); ?>" class="comment-body<?php
    if ($comments->levels > 0) {
        echo ' comment-child';
        $comments->levelsAlt(' comment-level-odd', ' comment-level-even');
    } else {
        echo ' comment-parent';
    }
    $comments->alt(' comment-odd', ' comment-even');
    echo $commentClass;
    ?>">
        <div id="<?php $comments->theId(); ?>" class="comment-inner">
            <div class="comment-author-avatar">
                <?php $comments->gravatar('40', ''); ?>
            </div>
            <div class="comment-main">
                <div class="comment-meta">
                    <cite class="comment-author" itemprop="creator" itemscope itemtype="http://schema.org/Person">
                        <?php $comments->author(); ?>
                    </cite>
                    <span class="comment-time">
                        <a href="<?php $comments->permalink(); ?>" itemprop="url">
                            <time datetime="<?php $comments->date('c'); ?>" itemprop="commentTime"><?php $comments->date('Y-m-d H:i'); ?></time>
                        </a>
                    </span>
                    <span class="comment-reply">
                        <?php $comments->reply(_t('回复')); ?>
                    </span>
                </div>
                <div class="comment-content" itemprop="commentText">
                    <?php $comments->content(); ?>
                </div>
            </div>
        </div>
        <?php if ($comments->children) { ?>
            <div class="comment-children">
                <?php $comments->threadedComments($options); ?>
            </div>
        <?php } ?>
    </li>
<?php
}

