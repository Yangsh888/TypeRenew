<?php
include 'common.php';

if ($user->hasLogin()) {
    $response->redirect($options->adminUrl);
}

$db = \Typecho\Db::get();
$tokenRaw = (string) $request->filter('trim')->get('token');
$token = htmlspecialchars($tokenRaw, ENT_QUOTES, 'UTF-8');

$tokenValid = false;
$tokenError = '';
$email = '';

if ($tokenRaw !== '') {
    if (!\Utils\PasswordReset::isValidRawToken($tokenRaw)) {
        $tokenError = _t('重置链接无效或已过期');
    } else {
        $record = \Utils\PasswordReset::findActiveRecordByToken($db, $tokenRaw);
        if ($record) {
            $tokenValid = true;
            $email = (string) ($record['email'] ?? '');
        } else {
            $tokenError = _t('重置链接无效或已过期');
        }
    }
} else {
    $tokenError = _t('缺少重置令牌');
}

$menu->title = _t('重置密码');

$bodyClass = 'body-100 tr-auth-forest';

include 'header.php';
include 'auth.php';
?>
<?php tr_auth_open([
    'label' => _t('重置密码'),
    'heading' => (string) $options->software,
    'description' => _t('设置新的登录密码'),
    'heroTitle' => (string) $options->title,
    'heroSubtitle' => _t('轻量化后台管理'),
    'heroFoot' => '&copy; ' . date('Y') . ' Typecho Team'
]); ?>
<?php if (!$tokenValid): ?>
<div class="tr-auth-notice" role="status">
    <p><?php echo htmlspecialchars($tokenError, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<a class="tr-auth-link tr-auth-link-center" href="<?php $options->adminUrl('forgot.php'); ?>"><?php _e('重新获取重置链接'); ?></a>
<?php else: ?>
<form action="<?php $security->index('/action/reset'); ?>" method="post" name="reset" role="form" class="tr-auth-form">
    <div class="tr-auth-field">
        <label for="password"><?php _e('新密码'); ?></label>
        <input type="password" id="password" name="password" placeholder="<?php _e('请输入 %d-%d 位密码', \Utils\Password::minLength(), \Utils\Password::maxLength()); ?>" required autofocus />
    </div>
    <div class="tr-auth-field">
        <label for="confirm"><?php _e('确认密码'); ?></label>
        <input type="password" id="confirm" name="confirm" placeholder="<?php _e('请再次输入密码'); ?>" required />
    </div>
    <div>
        <button type="submit" class="tr-auth-btn"><?php _e('重置密码'); ?></button>
        <input type="hidden" name="token" value="<?php echo $token; ?>" />
    </div>
</form>
<?php endif; ?>

<div class="tr-auth-divider"><span><?php _e('或者'); ?></span></div>
<a class="tr-auth-link tr-auth-link-center" href="<?php $options->adminUrl('login.php'); ?>"><?php _e('返回登录'); ?></a>
<div class="tr-auth-footlink">
    <a class="tr-auth-link" href="<?php $options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
</div>
<?php tr_auth_close(); ?>
<?php
include 'auth-js.php';
include 'footer.php';
?>
