<?php
include 'common.php';

if ($user->hasLogin() || !$options->allowRegister) {
    $response->redirect($options->siteUrl);
}
$rememberName = htmlspecialchars(\Typecho\Cookie::get('__typecho_remember_name', ''));
$rememberMail = htmlspecialchars(\Typecho\Cookie::get('__typecho_remember_mail', ''));
\Typecho\Cookie::delete('__typecho_remember_name');
\Typecho\Cookie::delete('__typecho_remember_mail');

$bodyClass = 'body-100 tr-auth-forest';

include 'header.php';
?>
<div class="tr-auth" role="main" aria-label="<?php _e('注册'); ?>">
    <div class="tr-auth-switch" aria-label="<?php _e('主题切换'); ?>">
        <button type="button" class="tr-auth-switch-btn" id="trAuthThemeBtn" aria-haspopup="true" aria-expanded="false"></button>
        <div class="tr-auth-switch-menu" id="trAuthThemeMenu" role="menu" aria-label="<?php _e('主题'); ?>"></div>
    </div>
    <section class="tr-auth-hero" aria-hidden="true">
        <div class="tr-auth-hero-inner">
            <div class="tr-auth-hero-title"><?php echo htmlspecialchars($options->title, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="tr-auth-hero-subtitle"><?php _e('轻量化管理后台，由 TypeRenew 焕新呈现'); ?></div>
        </div>
        <div class="tr-auth-hero-foot">&copy; <?php echo date('Y'); ?> TypeRenew Team</div>
    </section>
    <section class="tr-auth-panel">
        <div class="tr-auth-box">
            <div class="tr-auth-heading">
                <h1><?php echo htmlspecialchars($options->software, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php _e('创建一个新账号'); ?></p>
            </div>

            <form action="<?php $options->registerAction(); ?>" method="post" name="register" role="form" class="tr-auth-form">
                <div class="tr-auth-field">
                    <label for="name"><?php _e('用户名'); ?></label>
                    <input type="text" id="name" name="name" placeholder="<?php _e('请输入用户名'); ?>" value="<?php echo $rememberName; ?>" required autofocus />
                </div>
                <div class="tr-auth-field">
                    <label for="mail"><?php _e('邮箱'); ?></label>
                    <input type="email" id="mail" name="mail" placeholder="<?php _e('请输入邮箱'); ?>" value="<?php echo $rememberMail; ?>" required />
                </div>
                <div class="tr-auth-field">
                    <label for="password"><?php _e('密码'); ?></label>
                    <input type="password" id="password" name="password" placeholder="<?php _e('请输入 8-72 位密码'); ?>" required />
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
