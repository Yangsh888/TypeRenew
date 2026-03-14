<?php

namespace Widget;

use Typecho\Db;
use Utils\Password;
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

        if (strlen($password) < 8 || strlen($password) > 72) {
            Notice::alloc()->set(_t('密码长度需在 8-72 位之间'), 'error');
            $this->response->goBack();
        }

        if ($password !== $confirm) {
            Notice::alloc()->set(_t('两次输入的密码不一致'), 'error');
            $this->response->goBack();
        }

        $this->cleanupExpired();

        $records = $this->db->fetchAll(
            $this->db->select()->from('table.password_resets')
                ->where('used = ?', 0)
                ->where('expires > ?', time())
        );

        $record = null;
        foreach ($records as $r) {
            if (password_verify($token, (string) ($r['token'] ?? ''))) {
                $record = $r;
                break;
            }
        }

        if (!$record) {
            Notice::alloc()->set(_t('重置链接无效或已过期，请重新获取'), 'error');
            $this->response->redirect($this->options->adminUrl('forgot.php'));
        }

        $recordId = (int) ($record['id'] ?? 0);
        $recordEmail = (string) ($record['email'] ?? '');

        $locked = $this->db->query(
            $this->db->update('table.password_resets')
                ->rows(['used' => 1, 'updated' => time()])
                ->where('id = ? AND used = ?', $recordId, 0)
        );

        if (!$locked) {
            Notice::alloc()->set(_t('重置链接已被使用，请重新获取'), 'error');
            $this->response->redirect($this->options->adminUrl('forgot.php'));
        }

        $hashedPassword = Password::hash($password);

        $this->db->query(
            $this->db->update('table.users')
                ->rows(['password' => $hashedPassword])
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
