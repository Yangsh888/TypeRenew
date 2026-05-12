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
        if (!$this->request->isPost()) {
            $this->response->setStatus(405)->throwContent(_t('Method Not Allowed'), 'text/plain');
            return;
        }

        $this->security->protect();

        if ($this->user->hasLogin()) {
            $this->response->redirect($this->options->adminUrl);
        }

        $forgotUrl = Common::url('forgot.php', $this->options->adminUrl);
        $token = $this->request->filter('trim')->getInput('token', '');
        $password = $this->request->getInput('password', '');
        $confirm = $this->request->getInput('confirm', '');

        if (!PasswordReset::isValidRawToken($token)) {
            Notice::alloc()->set(_t('重置链接无效或已过期，请重新获取'), 'error');
            $this->response->redirect($forgotUrl);
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

        PasswordReset::cleanupExpired($this->db);

        $record = PasswordReset::findActiveRecordByToken($this->db, $token);

        if (!$record) {
            Notice::alloc()->set(_t('重置链接无效或已过期，请重新获取'), 'error');
            $this->response->redirect($forgotUrl);
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
            $this->response->redirect($forgotUrl);
        }

        $hashedPassword = Password::hash($password);
        $authCode = bin2hex(random_bytes(16));

        $updatedRows = $this->db->query(
            $this->db->update('table.users')
                ->rows([
                    'password' => $hashedPassword,
                    'authCode' => $authCode
                ])
                ->where('mail = ?', $recordEmail)
        );

        if (!$updatedRows) {
            Notice::alloc()->set(_t('用户不存在或已被删除，请重新获取重置链接'), 'error');
            $this->response->redirect($forgotUrl);
        }

        $this->db->query(
            $this->db->delete('table.password_resets')
                ->where('email = ?', $recordEmail)
                ->where('id <> ?', $recordId)
        );

        Notice::alloc()->set(_t('密码已重置，请使用新密码登录'), 'success');
        $this->response->redirect(Common::url('login.php', $this->options->adminUrl));
    }
}
