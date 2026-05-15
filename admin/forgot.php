<?php
include 'common.php';

if ($user->hasLogin()) {
    $response->redirect($options->adminUrl);
}

$mailEnabled = (int) ($options->mailEnable ?? 0) === 1;

$menu->title = _t('找回密码');

$bodyClass = 'body-100 tr-auth-forest';

include 'header.php';
include 'auth.php';
?>
<?php tr_auth_open([
    'label' => _t('找回密码'),
    'heroSubtitle' => _t('轻量化后台管理'),
    'heroFoot' => '&copy; ' . date('Y') . ' TypeRenew Team'
]); ?>
<?php if (!$mailEnabled): ?>
<div class="tr-auth-notice" role="status">
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
<?php tr_auth_close(); ?>
<?php
include 'auth-js.php';
include 'footer.php';
?>
