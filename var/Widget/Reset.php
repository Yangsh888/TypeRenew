<?php

namespace Widget;

use Typecho\Common;
use Typecho\Db;
use Utils\Password;
use Utils\PasswordReset;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Reset extends Users implements ActionInterface
{
    public function action(): void
    {
        $this->security->protect();

        if ($this->user->hasLogin()) {
            $this->response->redirect($this->options->adminUrl);
        }

        $token = trim((string) $this->request->get('token'));
        $password = (string) $this->request->get('password');
        $confirm = (string) $this->request->get('confirm');

        if (!PasswordReset::isValidRawToken($token)) {
            Notice::alloc()->set(_t('重置链接无效或已过期，请重新获取'), 'error');
            $this->response->redirect($this->options->adminUrl('forgot.php'));
        }

        if (!Password::validateLength($password)) {
            Notice::alloc()->set(
                _t('密码长度需在 %d-%d 位之间', Password::minLength(), Password::maxLength()),
                'error'
            );
            $this->response->goBack();
        }

        if ($password !== $confirm) {
            Notice::alloc()->set(_t('两次输入的密码不一致'), 'error');
            $this->response->goBack();
        }

        $this->cleanupExpired();

        $record = PasswordReset::findActiveRecordByToken($this->db, $token);

        if (!$record) {
            Notice::alloc()->set(_t('重置链接无效或已过期，请重新获取'), 'error');
            $this->response->redirect($this->options->adminUrl('forgot.php'));
        }

        $recordId = (int) ($record['id'] ?? 0);
        $recordEmail = (string) ($record['email'] ?? '');

        $locked = $this->db->query(
            $this->db->update('table.password_resets')
                ->rows(['used' => 1])
                ->where('id = ? AND used = ?', $recordId, 0)
        );

        if (!$locked) {
            Notice::alloc()->set(_t('重置链接已被使用，请重新获取'), 'error');
            $this->response->redirect($this->options->adminUrl('forgot.php'));
        }

        $hashedPassword = Password::hash($password);
        $authCode = function_exists('openssl_random_pseudo_bytes')
            ? bin2hex(openssl_random_pseudo_bytes(16))
            : sha1(Common::randString(20));

        $this->db->query(
            $this->db->update('table.users')
                ->rows([
                    'password' => $hashedPassword,
                    'authCode' => $authCode
                ])
                ->where('mail = ?', $recordEmail)
        );

        $this->db->query(
            $this->db->delete('table.password_resets')
                ->where('email = ?', $recordEmail)
                ->where('id <> ?', $recordId)
        );

        Notice::alloc()->set(_t('密码已重置，请使用新密码登录'), 'success');
        $this->response->redirect($this->options->adminUrl('login.php'));
    }

    private function cleanupExpired(): void
    {
        $this->db->query(
            $this->db->delete('table.password_resets')
                ->where('expires < ?', time())
        );
    }
}
