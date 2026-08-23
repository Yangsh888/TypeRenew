<?php

namespace Widget;

use Typecho\Common;
use Utils\LoginGuard;
use Utils\Password;
use Utils\PasswordReset;
use Utils\Session;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Reset extends Users implements ActionInterface
{
    public function action(): void
    {
        if (!$this->request->isPost()) {
            $this->response->setStatus(405)->throwContent(_t('Method Not Allowed'));
            return;
        }

        $this->security->protect();

        if ($this->user->hasLogin()) {
            $this->response->redirect($this->options->adminUrl);
        }

        $token = trim((string) $this->request->get('token'));
        $password = (string) $this->request->get('password');
        $confirm = (string) $this->request->get('confirm');

        $ip = $this->request->getIp();
        $retryAfter = LoginGuard::retryAfter($this->db, LoginGuard::SCOPE_RESET, $ip);

        if ($retryAfter > 0) {
            Notice::alloc()->set(
                _t('重置请求过于频繁，请在 %d 分钟后重试', (int) ceil($retryAfter / 60)),
                'error'
            );
            $this->response->redirect(Common::url('login.php', $this->options->adminUrl));
        }

        if (!PasswordReset::isValidRawToken($token)) {
            LoginGuard::recordFailure($this->db, LoginGuard::SCOPE_RESET, $ip);
            Notice::alloc()->set(_t('重置链接无效或已过期，请重新获取'), 'error');
            $this->response->redirect(Common::url('forgot.php', $this->options->adminUrl));
        }

        if (!Password::validateLength($password)) {
            Notice::alloc()->set(
                _t('密码至少需要 %d 个字符，且不能超过 %d 字节', Password::minLength(), Password::maxLength()),
                'error'
            );
            $this->response->goBack();
        }

        if ($password !== $confirm) {
            Notice::alloc()->set(_t('两次输入的密码不一致'), 'error');
            $this->response->goBack();
        }

        $hashedPassword = Password::hash($password);
        $authCode = bin2hex(Common::secureRandomBytes(16));
        try {
            $applied = PasswordReset::apply($this->db, $token, $hashedPassword, $authCode);
        } catch (\Throwable $throwable) {
            error_log('Widget.Reset.apply: ' . $throwable->getMessage());
            Notice::alloc()->set(_t('密码重置暂时失败，请稍后重试'), 'error');
            $this->response->goBack();
            return;
        }

        if (!$applied) {
            LoginGuard::recordFailure($this->db, LoginGuard::SCOPE_RESET, $ip);
            Notice::alloc()->set(_t('重置链接无效、已过期或已被使用，请重新获取'), 'error');
            $this->response->redirect(Common::url('forgot.php', $this->options->adminUrl));
        }

        Session::rotate();
        Notice::alloc()->set(_t('密码已重置，请使用新密码登录'), 'success');
        $this->response->redirect(Common::url('login.php', $this->options->adminUrl));
    }
}
