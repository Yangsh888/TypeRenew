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
    if (!preg_match('/^[a-f0-9]{64}$/', $tokenRaw)) {
        $tokenError = _t('重置链接无效或已过期');
    } else {
    $records = $db->fetchAll(
        $db->select()->from('table.password_resets')
            ->where('used = ?', 0)
            ->where('expires > ?', time())
    );
    $record = null;
    foreach ($records as $r) {
        if (password_verify($tokenRaw, (string) ($r['token'] ?? ''))) {
            $record = $r;
            break;
        }
    }
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
?>
<div class="tr-auth" role="main" aria-label="<?php _e('重置密码'); ?>">
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
                <p><?php _e('设置新密码'); ?></p>
            </div>

            <?php if (!$tokenValid): ?>
            <div class="tr-auth-notice">
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
