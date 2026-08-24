<?php

namespace Widget;

use Typecho\Common;
use Typecho\Db;
use Typecho\Mail\Queue;
use Typecho\Mail\Template;
use Utils\LoginGuard;
use Utils\PasswordReset;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Forgot extends Users implements ActionInterface
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

        $mailEnabled = (int) ($this->options->mailEnable ?? 0) === 1;
        if (!$mailEnabled) {
            Notice::alloc()->set(_t('邮件系统未启用，无法使用密码找回功能'), 'error');
            $this->response->goBack();
        }

        $mail = trim((string) $this->request->get('mail'));

        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            Notice::alloc()->set(_t('请输入有效的邮箱地址'), 'error');
            $this->response->goBack();
        }

        $ip = $this->request->getIp();
        $retryAfter = LoginGuard::retryAfter($this->db, LoginGuard::SCOPE_RESET, $ip, $mail);

        if ($retryAfter > 0) {
            Notice::alloc()->set(
                _t('重置请求过于频繁，请在 %d 分钟后重试', (int) ceil($retryAfter / 60)),
                'error'
            );
            $this->response->goBack();
        }

        LoginGuard::recordFailure($this->db, LoginGuard::SCOPE_RESET, $ip, $mail);
        PasswordReset::cleanupExpired($this->db);

        $recent = $this->db->fetchRow(
            $this->db->select('created')
                ->from('table.password_resets')
                ->where('email = ?', $mail)
                ->where('created > ?', $this->options->time - 60)
                ->limit(1)
        );

        if ($recent) {
            Notice::alloc()->set(_t('请求过于频繁，请稍后再试'), 'error');
            $this->response->goBack();
        }

        $user = $this->db->fetchRow(
            $this->db->select('uid', 'name', 'screenName', 'mail')
                ->from('table.users')
                ->where('mail = ?', $mail)
                ->limit(1)
        );

        if (!$user) {
            Notice::alloc()->set(_t('如果该邮箱已注册，您将收到重置邮件'), 'success');
            $this->response->goBack();
        }

        $rawToken = PasswordReset::generateToken();
        $tokenHash = PasswordReset::hashToken($rawToken);
        $expires = time() + 1800;

        $resetUrl = Common::url(
            'reset.php?token=' . $rawToken,
            $this->options->adminUrl
        );

        $this->db->query('BEGIN');

        try {
            $this->db->query(
                $this->db->delete('table.password_resets')
                    ->where('email = ?', $mail)
            );

            $this->db->query(
                $this->db->insert('table.password_resets')->rows([
                    'email' => $mail,
                    'token' => $tokenHash,
                    'created' => time(),
                    'expires' => $expires,
                    'used' => 0
                ])
            );

            if (!$this->sendResetMail($user, $resetUrl, $expires)) {
                throw new \RuntimeException(_t('密码重置邮件入队失败'));
            }

            $this->db->query('COMMIT');
        } catch (\Throwable $throwable) {
            try {
                $this->db->query('ROLLBACK');
            } catch (\Throwable) {
            }

            error_log('Widget.Forgot.enqueue: ' . $throwable->getMessage());
            Notice::alloc()->set(_t('重置邮件暂时无法发送，请稍后重试'), 'error');
            $this->response->goBack();
            return;
        }

        Notice::alloc()->set(_t('如果该邮箱已注册，您将收到重置邮件'), 'success');
        $this->response->goBack();
    }

    private function sendResetMail(array $user, string $resetUrl, int $expires): bool
    {
        $siteTitle = (string) ($this->options->title ?? 'TypeRenew');
        $siteUrl = (string) ($this->options->siteUrl ?? '');
        $expiresAt = $this->options->formatDateTime($expires, 'Y-m-d H:i:s');

        $vars = [
            'subject' => _t('密码重置请求'),
            'siteTitle' => $siteTitle,
            'siteUrl' => $siteUrl,
            'mail' => (string) ($user['mail'] ?? ''),
            'resetUrl' => $resetUrl,
            'expiresAt' => $expiresAt
        ];

        $html = Template::render('reset', $vars, $this->options);
        $subject = $vars['subject'];

        $msg = Queue::buildMessage($this->options, (string) ($user['mail'] ?? ''), $subject, $html);

        return Queue::enqueue('reset', $msg, Db::get(), $this->options);
    }
}
