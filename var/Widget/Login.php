<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Validate;
use Utils\LoginGuard;
use Utils\Session;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Login extends Users implements ActionInterface
{
    public function action()
    {
        if (!$this->request->isPost()) {
            $this->response->setStatus(405)->throwContent(_t('Method Not Allowed'));
            return;
        }
        $this->security->protect();

        if ($this->user->hasLogin()) {
            $this->response->redirect($this->options->index);
        }

        $validator = new Validate();
        $validator->addRule('name', 'required', _t('请输入用户名'));
        $validator->addRule('password', 'required', _t('请输入密码'));
        $rememberTtl = 30 * 24 * 3600;
        $rememberExpire = time() + $rememberTtl;

        if ($this->request->is('remember=1')) {
            Cookie::set('__typecho_remember_remember', 1, $rememberExpire);
        } elseif (Cookie::get('__typecho_remember_remember')) {
            Cookie::delete('__typecho_remember_remember');
        }

        if ($error = $validator->run($this->request->from('name', 'password'))) {
            Cookie::set('__typecho_remember_name', $this->request->get('name'));

            Notice::alloc()->set($error);
            $this->response->goBack();
        }

        $name = (string) $this->request->get('name', '');
        $referer = (string) $this->request->get('referer', '');
        $ip = $this->request->getIp();
        $retryAfter = LoginGuard::retryAfter($this->db, LoginGuard::SCOPE_LOGIN, $ip, $name);

        if ($retryAfter > 0) {
            Cookie::set('__typecho_remember_name', $name);
            Notice::alloc()->set(
                _t('登录尝试过于频繁，请在 %d 分钟后重试', (int) ceil($retryAfter / 60)),
                'error'
            );
            $this->response->goBack('?referer=' . urlencode($referer));
        }

        $valid = $this->user->login(
            $name,
            (string) $this->request->get('password', ''),
            false,
            $this->request->is('remember=1') ? $rememberTtl : 0
        );

        if (!$valid) {
            LoginGuard::recordFailure($this->db, LoginGuard::SCOPE_LOGIN, $ip, $name);
            sleep(3);

            self::pluginHandle()->call(
                'loginFailure',
                $this->user,
                $name,
                null,
                $this->request->is('remember=1')
            );

            Cookie::set('__typecho_remember_name', $name);
            Notice::alloc()->set(_t('用户名或密码无效'), 'error');
            $this->response->goBack('?referer=' . urlencode($referer));
        }

        LoginGuard::clear($this->db, LoginGuard::SCOPE_LOGIN, $ip, $name);
        Session::rotate();

        self::pluginHandle()->call(
            'loginSuccess',
            $this->user,
            $name,
            null,
            $this->request->is('remember=1')
        );

        if (!empty($this->request->referer)) {
            if ($this->isSafeRedirect((string) $this->request->referer)) {
                $this->response->redirect($this->request->referer);
            }
        } elseif (!$this->user->pass('contributor', true)) {
            $this->response->redirect($this->options->profileUrl);
        }

        $this->response->redirect($this->options->adminUrl);
    }

    private function isSafeRedirect(string $target): bool
    {
        $target = trim($target);
        if ($target === '') {
            return false;
        }

        foreach ([(string) $this->options->adminUrl, (string) $this->options->siteUrl] as $baseUrl) {
            $base = parse_url($baseUrl);
            $candidate = parse_url($target);

            if (!is_array($base) || !is_array($candidate)) {
                continue;
            }

            $baseHost = strtolower((string) ($base['host'] ?? ''));
            $candidateHost = strtolower((string) ($candidate['host'] ?? ''));
            if ($baseHost === '' || $candidateHost === '' || $baseHost !== $candidateHost) {
                continue;
            }

            $baseScheme = strtolower((string) ($base['scheme'] ?? ''));
            $candidateScheme = strtolower((string) ($candidate['scheme'] ?? ''));
            if ($baseScheme !== $candidateScheme) {
                continue;
            }

            $basePort = (int) ($base['port'] ?? ($baseScheme === 'https' ? 443 : 80));
            $candidatePort = (int) ($candidate['port'] ?? ($candidateScheme === 'https' ? 443 : 80));
            if ($basePort !== $candidatePort) {
                continue;
            }

            $basePath = rtrim((string) ($base['path'] ?? '/'), '/');
            $candidatePath = (string) ($candidate['path'] ?? '/');
            if ($basePath !== '' && $basePath !== '/' && !str_starts_with($candidatePath, $basePath . '/')
                && $candidatePath !== $basePath) {
                continue;
            }

            return Common::url($candidatePath . (!empty($candidate['query']) ? '?' . $candidate['query'] : '')
                . (!empty($candidate['fragment']) ? '#' . $candidate['fragment'] : ''), $baseUrl) === $target;
        }

        return false;
    }
}
