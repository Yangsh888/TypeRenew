<?php

namespace Widget;

use Typecho\Db;
use Typecho\Widget\Exception;
use Widget\Base\Options as BaseOptions;
use Typecho\Mail\Queue;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Mail extends BaseOptions implements ActionInterface
{
    private const CRON_SIGN_TTL = 600;
    private const UNSUB_TOKEN_TTL = 2592000;

    public function async()
    {
        $data = $this->request->get('@json');
        $token = (string) ($data['token'] ?? '');

        if (!Queue::verifyAsyncToken($token, (string) ($this->options->secret ?? ''), 5)) {
            throw new Exception(_t('禁止访问'), 403);
        }

        if (!Queue::isAsyncRequesterAllowed($this->options, (string) $this->request->getIp())) {
            throw new Exception(_t('异步回调来源未被允许'), 403);
        }

        $this->response->throwFinish();

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(30);
        }

        Queue::deliverBatch(Db::get(), $this->options, (int) ($this->options->mailBatchSize ?? 30));
    }

    public function deliver()
    {
        if (!$this->verifyDeliverAuth()) {
            $this->response->throwJson(['success' => 0, 'message' => 'No permission']);
        }

        $result = Queue::deliverBatch(Db::get(), $this->options, (int) ($this->options->mailBatchSize ?? 50));
        $this->response->throwJson(['success' => 1] + $result);
    }

    public function unsub()
    {
        $token = (string) $this->request->get('token');
        [$email, $scope] = $this->parseUnsubToken($token);

        if ($email === '' || $scope === '') {
            throw new Exception(_t('链接无效'), 400);
        }

        $ok = Queue::unsub($email, $scope, Db::get());
        $message = $ok ? _t('退订成功') : _t('退订失败');
        $mask = preg_replace('/(^.).*(@.*$)/u', '$1***$2', $email) ?: $email;
        $this->response->setContentType('text/html');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</title></head><body style="margin:0;padding:40px 16px;background:#f6f6f3;color:#222;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,PingFang SC,Microsoft YaHei,sans-serif;">'
            . '<div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:18px 20px;">'
            . '<div style="font-size:18px;font-weight:600;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="margin-top:10px;font-size:14px;color:#666;">' . htmlspecialchars($mask, ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div></body></html>';
        exit;
    }

    public function action()
    {
        $this->on($this->request->isPost() && $this->request->is('do=async'))->async();
        $this->on($this->request->is('do=deliver'))->deliver();
        $this->on($this->request->is('do=unsub'))->unsub();
    }

    private function parseUnsubToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['', ''];
        }

        $b64 = strtr($token, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($b64, true);
        if ($raw === false) {
            return ['', ''];
        }

        $parts = explode('|', $raw);
        if (count($parts) < 3) {
            return ['', ''];
        }

        if ((string) ($parts[0] ?? '') === 'v2' && count($parts) === 5) {
            return $this->parseUnsubTokenV2($parts);
        }

        return ['', ''];
    }

    private function parseUnsubTokenV2(array $parts): array
    {
        [, $email, $scope, $ts, $sigB64] = $parts;
        $email = strtolower(trim((string) $email));
        $scope = trim((string) $scope);
        $ts = (int) $ts;
        $sig = base64_decode((string) $sigB64, true);
        if ($sig === false) {
            return ['', ''];
        }

        if ($ts <= 0 || abs(time() - $ts) > self::UNSUB_TOKEN_TTL) {
            return ['', ''];
        }

        $payload = 'v2|' . $email . '|' . $scope . '|' . $ts;
        $expected = hash_hmac('sha256', $payload, (string) ($this->options->secret ?? ''), true);
        if (!hash_equals($expected, $sig)) {
            return ['', ''];
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['', ''];
        }

        if (!in_array($scope, ['reply', 'owner', 'pending'], true)) {
            return ['', ''];
        }

        return [$email, $scope];
    }

    private function verifyDeliverAuth(): bool
    {
        if (!$this->request->isPost()) {
            return false;
        }

        $stored = trim((string) ($this->options->mailCronKey ?? ''));
        if ($stored === '') {
            return false;
        }

        $json = $this->request->get('@json');
        if (!is_array($json)) {
            $json = [];
        }

        $ts = (string) ($this->request->getHeader('X-Typecho-Mail-Ts')
            ?? ($json['ts'] ?? ''));
        $sign = strtolower(trim((string) ($this->request->getHeader('X-Typecho-Mail-Sign')
            ?? ($json['sign'] ?? ''))));

        if ($ts !== '' && $sign !== '' && ctype_digit($ts)) {
            $time = (int) $ts;
            if (abs(time() - $time) <= self::CRON_SIGN_TTL) {
                $expected = hash_hmac('sha256', $ts . '|deliver', $stored);
                if (hash_equals($expected, $sign)) {
                    return Queue::guardReplay('mail_deliver', $ts . '|' . $sign, self::CRON_SIGN_TTL);
                }
            }
        }

        return false;
    }
}
