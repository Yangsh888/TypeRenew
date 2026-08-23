<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<div id="comments" class="comments-section">
    <?php $this->comments()->to($comments); ?>
    <div class="comments-header">
        <span class="comments-title"><?php $comments->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?></span>
        <?php if ($this->user->hasLogin()) : ?>
            <a class="comments-edit-link" href="<?php $this->options->adminUrl('manage-comments.php'); ?>"><?php _e('管理'); ?></a>
        <?php endif; ?>
    </div>

    <?php if ($this->allow('comment')) : ?>
        <div id="respond" class="comment-respond">
            <span class="respond-title"><?php _e('添加新评论'); ?></span>
            <form method="post" action="<?php $this->commentUrl(); ?>" id="comment-form" role="form" class="comment-form">
                <div class="comment-user-info">
                    <div class="form-group">
                        <label for="author" class="sr-only"><?php _e('昵称'); ?></label>
                        <input type="text" name="author" id="author" class="form-input" value="<?php echo $this->user->hasLogin() ? $this->user->screenName() : $this->remember('author', true); ?>" placeholder="<?php _e('昵称'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="mail" class="sr-only"><?php _e('邮箱'); ?></label>
                        <input type="email" name="mail" id="mail" class="form-input" value="<?php echo $this->user->hasLogin() ? $this->user->mail() : $this->remember('mail', true); ?>" placeholder="<?php _e('邮箱'); ?>"<?php if ($this->options->commentsRequireMail && !$this->user->hasLogin()) : ?> required<?php endif; ?>>
                    </div>
                    <?php if ($this->options->commentsRequireUrl) : ?>
                    <div class="form-group">
                        <label for="url" class="sr-only"><?php _e('网站'); ?></label>
                        <input type="url" name="url" id="url" class="form-input" value="<?php $this->remember('url', true); ?>" placeholder="<?php _e('网站'); ?>"<?php if (!$this->user->hasLogin()) : ?> required<?php endif; ?>>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="textarea" class="sr-only"><?php _e('内容'); ?></label>
                    <textarea name="text" id="textarea" class="form-textarea" rows="3" placeholder="<?php _e('在这里写下你的评论...'); ?>" required><?php $this->remember('text'); ?></textarea>
                </div>
                <div class="comment-form-actions">
                    <button type="submit" class="btn-submit"><?php _e('提交评论'); ?></button>
                </div>
            </form>
            <?php if ($this->user->hasLogin()) : ?>
                <p class="comment-logged-in"><?php _e('已登录为'); ?> <a href="<?php $this->options->adminUrl('profile.php'); ?>"><?php $this->user->screenName(); ?></a>. <a href="<?php $this->options->logoutUrl(); ?>"><?php _e('退出'); ?></a></p>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <p class="comment-closed"><?php _e('评论已关闭'); ?></p>
    <?php endif; ?>

    <?php $comments->listComments(); ?>
    <?php $comments->pageNav('«', '»', 1, '...'); ?>
</div>
