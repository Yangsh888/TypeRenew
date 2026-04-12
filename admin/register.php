<?php
include 'common.php';

if ($user->hasLogin() || !$options->allowRegister) {
    $response->redirect($options->siteUrl);
}
$rememberName = (string) \Typecho\Cookie::get('__typecho_remember_name', '');
$rememberMail = (string) \Typecho\Cookie::get('__typecho_remember_mail', '');
\Typecho\Cookie::delete('__typecho_remember_name');
\Typecho\Cookie::delete('__typecho_remember_mail');

$bodyClass = 'body-100 tr-auth-forest';

include 'header.php';
include 'auth.php';
?>
<?php tr_auth_open([
    'label' => _t('注册'),
    'heading' => (string) $options->software,
    'description' => _t('创建一个新账号'),
    'heroTitle' => (string) $options->title,
    'heroSubtitle' => _t('轻量化管理后台，由 TypeRenew 焕新呈现'),
    'heroFoot' => '&copy; ' . date('Y') . ' TypeRenew Team'
]); ?>
<form action="<?php $options->registerAction(); ?>" method="post" name="register" role="form" class="tr-auth-form">
    <div class="tr-auth-field">
        <label for="name"><?php _e('用户名'); ?></label>
        <input type="text" id="name" name="name" placeholder="<?php _e('请输入用户名'); ?>" value="<?php echo htmlspecialchars($rememberName, ENT_QUOTES, 'UTF-8'); ?>" required autofocus />
    </div>
    <div class="tr-auth-field">
        <label for="mail"><?php _e('邮箱'); ?></label>
        <input type="email" id="mail" name="mail" placeholder="<?php _e('请输入邮箱'); ?>" value="<?php echo htmlspecialchars($rememberMail, ENT_QUOTES, 'UTF-8'); ?>" required />
    </div>
    <div class="tr-auth-field">
        <label for="password"><?php _e('密码'); ?></label>
        <input type="password" id="password" name="password" placeholder="<?php _e('请输入 %d-%d 位密码', \Utils\Password::minLength(), \Utils\Password::maxLength()); ?>" required />
    </div>
    <div class="tr-auth-field">
        <label for="confirm"><?php _e('确认密码'); ?></label>
        <input type="password" id="confirm" name="confirm" placeholder="<?php _e('请再次输入密码'); ?>" required />
    </div>
    <div>
        <button type="submit" class="tr-auth-btn"><?php _e('注册'); ?></button>
    </div>
</form>

<div class="tr-auth-divider"><span><?php _e('已有账号'); ?></span></div>
<a class="tr-auth-link tr-auth-link-center" href="<?php $options->adminUrl('login.php'); ?>"><?php _e('返回登录'); ?></a>
<div class="tr-auth-footlink">
    <a class="tr-auth-link" href="<?php $options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
</div>
<?php tr_auth_close(); ?>
<?php
include 'auth-js.php';
include 'footer.php';
?>
