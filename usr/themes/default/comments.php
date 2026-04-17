<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $requireUrl = isset($this->options->commentsRequireUrl) ? $this->options->commentsRequireUrl : $this->options->commentsRequireURL; ?>

<div id="comments" class="comments-area">
    <?php $this->comments()->to($comments); ?>
    
    <div class="comments-wrapper">
        <?php if ($comments->have()): ?>
            <div class="comments-header">
                <?php $this->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?>
            </div>
            
            <div class="comments-body">
                <?php $comments->listComments([
                    'before'        => '<ul class="comment-list">',
                    'after'         => '</ul>',
                    'commentStatus' => _t('等待审核')
                ]); ?>
                
                <?php $comments->pageNav('&laquo;', '&raquo;'); ?>
            </div>
        <?php endif; ?>

        <?php if ($this->allow('comment')): ?>
            <div id="<?php $this->respondId(); ?>" class="respond">
                <div class="cancel-comment-reply">
                    <?php $comments->cancelReply(); ?>
                </div>
                
                <h3 id="response" class="respond-title"><?php _e('撰写评论'); ?></h3>
                <form method="post" action="<?php $this->commentUrl() ?>" id="comment-form" role="form">
                    <?php $security = $this->widget('\Widget\Security'); ?>
                    <input type="hidden" name="_" value="<?php echo htmlspecialchars($security->getToken($this->request->getRequestUrl()), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($this->user->hasLogin()): ?>
                        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: var(--sp-3);">
                            <?php _e('登录身份: '); ?><a href="<?php $this->options->profileUrl(); ?>" style="font-weight: 600; color: var(--text-primary);"><?php $this->user->screenName(); ?></a>. 
                            <a href="<?php $this->options->logoutUrl(); ?>" title="Logout"><?php _e('退出'); ?> &raquo;</a>
                        </p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--sp-3); margin-bottom: var(--sp-3);">
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="text" name="author" id="author" class="form-input" placeholder="<?php _e('称呼 *'); ?>" aria-label="<?php _e('称呼'); ?>" value="<?php $this->remember('author'); ?>" required />
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="email" name="mail" id="mail" class="form-input" placeholder="<?php _e('Email' . ($this->options->commentsRequireMail ? ' *' : '')); ?>" aria-label="<?php _e('Email'); ?>" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?> />
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="url" name="url" id="url" class="form-input" placeholder="<?php _e('网站' . ($requireUrl ? ' *' : '')); ?>" aria-label="<?php _e('网站'); ?>" value="<?php $this->remember('url'); ?>"<?php if ($requireUrl): ?> required<?php endif; ?> />
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="form-group" style="margin-bottom: var(--sp-3);">
                        <textarea name="text" id="textarea" class="form-input" placeholder="<?php _e('写点什么吧...'); ?>" aria-label="<?php _e('评论内容'); ?>" required><?php $this->remember('text'); ?></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; text-align: right;">
                        <button type="submit" class="btn-submit"><?php _e('提交评论'); ?></button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div style="padding: var(--sp-4); text-align: center; color: var(--text-tertiary); font-size: 0.9rem; background: var(--bg-secondary); border-top: 1px solid var(--border-color);">
                <?php _e('评论已关闭'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
