<?php
include 'common.php';

if ($user->hasLogin()) {
    $response->redirect($options->adminUrl);
}
$rememberName = htmlspecialchars(\Typecho\Cookie::get('__typecho_remember_name', ''));
\Typecho\Cookie::delete('__typecho_remember_name');

$mailEnabled = (int) ($options->mailEnable ?? 0) === 1;

$bodyClass = 'body-100 tr-auth-forest';

include 'header.php';
?>
<div class="tr-auth" role="main" aria-label="<?php _e('登录'); ?>">
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
                <p><?php _e('请登录以继续'); ?></p>
            </div>

            <form action="<?php $options->loginAction(); ?>" method="post" name="login" role="form" class="tr-auth-form">
                <div class="tr-auth-field">
                    <label for="name"><?php _e('用户名或邮箱'); ?></label>
                    <input type="text" id="name" name="name" value="<?php echo $rememberName; ?>" placeholder="<?php _e('请输入用户名或邮箱'); ?>" required autofocus />
                </div>
                <div class="tr-auth-field">
                    <label for="password"><?php _e('密码'); ?></label>
                    <input type="password" id="password" name="password" placeholder="<?php _e('请输入密码'); ?>" required />
                </div>
                <div class="tr-auth-row">
                    <label class="tr-auth-check" for="remember">
                        <input<?php if (\Typecho\Cookie::get('__typecho_remember_remember')): ?> checked<?php endif; ?> type="checkbox" name="remember" value="1" id="remember" />
                        <span><?php _e('下次自动登录'); ?></span>
                    </label>
                    <?php if ($mailEnabled): ?>
                    <a class="tr-auth-link" href="<?php $options->adminUrl('forgot.php'); ?>"><?php _e('忘记密码'); ?></a>
                    <?php else: ?>
                    <a class="tr-auth-link" href="<?php $options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="submit" class="tr-auth-btn"><?php _e('登录'); ?></button>
                    <input type="hidden" name="referer" value="<?php echo $request->filter('html')->get('referer'); ?>" />
                </div>
            </form>

            <?php if ($options->allowRegister): ?>
                <div class="tr-auth-divider"><span><?php _e('或者'); ?></span></div>
                <a class="tr-auth-link tr-auth-link-center" href="<?php $options->registerUrl(); ?>"><?php _e('创建一个新账号'); ?></a>
            <?php endif; ?>
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
