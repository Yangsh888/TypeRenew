<?php
include 'common.php';

if ($user->hasLogin()) {
    $response->redirect($options->adminUrl);
}
$rememberName = (string) \Typecho\Cookie::get('__typecho_remember_name', '');
\Typecho\Cookie::delete('__typecho_remember_name');

$mailEnabled = (int) ($options->mailEnable ?? 0) === 1;

$bodyClass = 'body-100 tr-auth-forest';

include 'header.php';
include 'auth.php';
?>
<?php tr_auth_open([
    'label' => _t('登录'),
    'description' => _t('请登录以继续')
]); ?>
<form action="<?php $options->loginAction(); ?>" method="post" name="login" role="form" class="tr-auth-form">
    <div class="tr-auth-field">
        <label for="name"><?php _e('用户名或邮箱'); ?></label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($rememberName, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php _e('请输入用户名或邮箱'); ?>" required autofocus />
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
<?php tr_auth_close(); ?>
<?php
include 'auth-js.php';
include 'footer.php';
?>
