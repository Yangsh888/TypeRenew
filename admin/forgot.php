<?php
include 'common.php';

if ($user->hasLogin()) {
    $response->redirect($options->adminUrl);
}

$mailEnabled = (int) ($options->mailEnable ?? 0) === 1;

$menu->title = _t('找回密码');

$bodyClass = 'body-100 tr-auth-forest';

include 'header.php';
?>
<div class="tr-auth" role="main" aria-label="<?php _e('找回密码'); ?>">
    <div class="tr-auth-switch" aria-label="<?php _e('主题切换'); ?>">
        <button type="button" class="tr-auth-switch-btn" id="trAuthThemeBtn" aria-haspopup="true" aria-expanded="false"></button>
        <div class="tr-auth-switch-menu" id="trAuthThemeMenu" role="menu" aria-label="<?php _e('主题'); ?>"></div>
    </div>
    <section class="tr-auth-hero" aria-hidden="true">
        <div class="tr-auth-hero-inner">
            <div class="tr-auth-hero-title"><?php echo htmlspecialchars($options->title, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="tr-auth-hero-subtitle"><?php _e('轻量化后台管理'); ?></div>
        </div>
        <div class="tr-auth-hero-foot">&copy; <?php echo date('Y'); ?> Typecho Team</div>
    </section>
    <section class="tr-auth-panel">
        <div class="tr-auth-box">
            <div class="tr-auth-heading">
                <h1><?php echo htmlspecialchars($options->software, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php _e('找回密码'); ?></p>
            </div>

            <?php if (!$mailEnabled): ?>
            <div class="tr-auth-notice">
                <p><?php _e('邮件系统未启用，无法使用密码找回功能。'); ?></p>
            </div>
            <?php else: ?>
            <form action="<?php $security->index('/action/forgot'); ?>" method="post" name="forgot" role="form" class="tr-auth-form">
                <div class="tr-auth-field">
                    <label for="mail"><?php _e('邮箱'); ?></label>
                    <input type="email" id="mail" name="mail" placeholder="<?php _e('请输入注册邮箱'); ?>" required autofocus />
                </div>
                <div>
                    <button type="submit" class="tr-auth-btn"><?php _e('发送重置链接'); ?></button>
                </div>
            </form>
            <?php endif; ?>

            <div class="tr-auth-divider"><span><?php _e('想起密码了？'); ?></span></div>
            <a class="tr-auth-link tr-auth-link-center" href="<?php $options->adminUrl('login.php'); ?>"><?php _e('返回登录'); ?></a>
            <div class="tr-auth-footlink">
                <a class="tr-auth-link" href="<?php $options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
            </div>
        </div>
    </section>
</div>
<?php 
include 'common-js.php';
?>
<script src="<?php $options->adminStaticUrl('js', 'auth-theme.js'); ?>"></script>
<?php
include 'footer.php';
?>
