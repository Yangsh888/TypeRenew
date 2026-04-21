<?php

namespace Typecho\Mail;

use Typecho\Common;
use Typecho\Db;
use Typecho\Http\Client;
use Typecho\Response;
use Widget\Options;

class Queue
{
    public static function enqueueComment(object $comment, string $event, Options $options): void
    {
        try {
            $jobs = Notify::buildFromComment($comment, $event, $options);
        } catch (\Throwable $e) {
            self::recordRuntimeError('build', $e->getMessage());
            return;
        }

        if (empty($jobs)) {
            self::clearRulesRuntimeError();
            return;
        }

        try {
            $db = Db::get();
            $now = time();

            foreach ($jobs as $job) {
                $payload = [
                    'to' => (string) $job['to'],
                    'toName' => (string) $job['toName'],
                    'subject' => (string) $job['subject'],
                    'html' => (string) $job['html'],
                    'text' => (string) $job['text'],
                    'meta' => [
                        'event' => $event,
                        'coid' => (int) ($comment->coid ?? 0),
                        'cid' => (int) ($comment->cid ?? 0),
                        'ownerId' => (int) ($comment->ownerId ?? 0)
                    ]
                ];
                $dedupeKey = self::dedupeKey($job, $payload);

                $rows = [
                    'type' => (string) $job['type'],
                    'status' => 'pending',
                    'attempts' => 0,
                    'lockedUntil' => 0,
                    'sendAt' => $now,
                    'created' => $now,
                    'updated' => $now,
                    'lastError' => '',
                    'dedupeKey' => $dedupeKey,
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)
                ];

                self::insertQueueRow($db, $rows);
            }
        } catch (\Throwable $e) {
            self::recordRuntimeError('enqueue', $e->getMessage());
            return;
        }
        self::clearRuntimeError();

        self::triggerDelivery($db, $options);
    }

    public static function enqueue(string $type, Message $message, Db $db, Options $options): void
    {
        $now = time();
        $payload = [
            'to' => (string) $message->to,
            'toName' => (string) $message->toName,
            'subject' => (string) $message->subject,
            'html' => (string) $message->html,
            'text' => (string) $message->text,
            'meta' => [
                'event' => $type,
                'coid' => 0,
                'cid' => 0,
                'ownerId' => 0
            ]
        ];

        $dedupeKey = sha1(json_encode([
            'type' => $type,
            'to' => strtolower(trim((string) $message->to)),
            'event' => $type,
            'ts' => $now
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        $rows = [
            'type' => $type,
            'status' => 'pending',
            'attempts' => 0,
            'lockedUntil' => 0,
            'sendAt' => $now,
            'created' => $now,
            'updated' => $now,
            'lastError' => '',
            'dedupeKey' => $dedupeKey,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)
        ];

        try {
            self::insertQueueRow($db, $rows);
            self::clearRuntimeError();
        } catch (\Throwable $e) {
            self::recordRuntimeError('enqueue', $e->getMessage());
            return;
        }

        self::triggerDelivery($db, $options);
    }

    private static function triggerDelivery(Db $db, Options $options): void
    {
        $mode = (string) ($options->mailQueueMode ?? 'async');
        if ($mode === 'sync') {
            self::deliverBatch($db, $options, 20);
        } elseif ($mode === 'async') {
            self::deliverBatch($db, $options, min(20, (int) ($options->mailBatchSize ?? 20)));
            self::requestAsync($options);
        }
    }

    public static function requestAsync(Options $options): bool
    {
        static $called;

        if ($called) {
            return true;
        }

        $called = true;
        Response::getInstance()->addBackgroundResponder(function () use ($options) {
            $fallback = static function () use ($options): void {
                self::deliverBatch(Db::get(), $options, min(20, (int) ($options->mailBatchSize ?? 20)));
            };
            $client = Client::get();
            if (!$client) {
                self::recordRuntimeError('async', 'http client unavailable');
                $fallback();
                return;
            }

            $serverIp = self::getServerIp();
            if (!self::isAsyncRequesterAllowed($options, $serverIp)) {
                self::recordRuntimeError('async', 'server ip not allowed: ' . $serverIp);
                $fallback();
                return;
            }

            $ts = time();
            $secret = (string) ($options->secret ?? '');
            $token = hash_hmac('sha256', 'async|' . $ts, $secret) . '|' . $ts;

            try {
                $client->setHeader('User-Agent', (string) ($options->generator ?? 'TypeRenew'))
                    ->setTimeout(2)
                    ->setJson([
                        'token' => $token,
                        'ts' => $ts
                    ])
                    ->send(Common::url('/action/mail?do=async', (string) $options->index));

                $status = $client->getResponseStatus();
                if ($status < 200 || $status >= 300) {
                    throw new \RuntimeException('async http status ' . $status);
                }
            } catch (\Throwable $e) {
                self::recordRuntimeError('async', $e->getMessage());
                $fallback();
            }
        });
        return true;
    }

    public static function verifyAsyncToken(string $token, string $secret, int $maxAge = 5): bool
    {
        $parts = explode('|', $token);
        if (count($parts) !== 2) {
            return false;
        }

        [$sig, $ts] = $parts;
        $ts = (int) $ts;
        if ($ts <= 0 || abs(time() - $ts) > $maxAge) {
            return false;
        }

        $expectedSig = hash_hmac('sha256', 'async|' . $ts, $secret);
        return hash_equals($expectedSig, $sig)
            && self::guardReplay('mail_async', $token, max(1, $maxAge));
    }

    public static function isAsyncRequesterAllowed(Options $options, ?string $ip = null): bool
    {
        $allowedIps = self::parseAllowedIps((string) ($options->mailAsyncIps ?? ''));
        if (empty($allowedIps)) {
            return true;
        }

        $ip = trim((string) $ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return in_array($ip, $allowedIps, true);
    }

    public static function guardReplay(string $scope, string $value, int $ttl): bool
    {
        $cache = \Typecho\Cache::getInstance();
        if (!$cache->enabled()) {
            return true;
        }

        return $cache->tryLock('replay:' . $scope . ':' . sha1($value), max(1, $ttl));
    }

    private static function parseAllowedIps(string $config): array
    {
        if ($config === '') {
            return [];
        }
        $ips = [];
        foreach (preg_split('/[\s,]+/', $config) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (filter_var($item, FILTER_VALIDATE_IP)) {
                $ips[] = $item;
            }
        }
        return $ips;
    }

    private static function getServerIp(): string
    {
        return $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? '127.0.0.1';
    }

    public static function deliverBatch(Db $db, Options $options, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $now = time();
        $lockedUntil = $now + 900;
        $cache = \Typecho\Cache::getInstance();

        $keepDays = (int) ($options->mailKeepDays ?? 7);
        if ($keepDays > 0) {
            self::maybeCleanup($db, $keepDays);
        }

        try {
            $candidates = $db->fetchAll(
                $db->select('id', 'type', 'payload', 'attempts')->from('table.mail_queue')
                    ->where('sendAt <= ?', $now)
                    ->where('(status = ? OR status = ? OR (status = ? AND lockedUntil < ?))', 'pending', 'failed', 'processing', $now)
                    ->order('id', Db::SORT_ASC)
                    ->limit($limit)
            );
        } catch (\Throwable $e) {
            return ['sent' => 0, 'failed' => 0, 'errors' => []];
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($candidates as $row) {
            $id = (int) $row['id'];
            $attempts = (int) $row['attempts'];
            $maxAttempts = max(1, min(10, (int) ($options->mailMaxAttempts ?? 3)));

            if ($attempts >= $maxAttempts) {
                $db->query($db->update('table.mail_queue')->rows([
                    'status' => 'dead',
                    'lockedUntil' => 0,
                    'updated' => $now
                ])->where('id = ? AND status <> ?', $id, 'sent'));
                continue;
            }

            $locked = $db->query(
                $db->update('table.mail_queue')->rows([
                    'status' => 'processing',
                    'lockedUntil' => $lockedUntil,
                    'updated' => $now
                ])->where('id = ? AND ((status = ? OR status = ?) OR (status = ? AND lockedUntil < ?)) AND (lockedUntil = 0 OR lockedUntil < ?)', $id, 'pending', 'failed', 'processing', $now, $now)
            );

            if (!$locked) {
                continue;
            }

            $cacheLockKey = 'mail:queue:send:' . $id;
            if ($cache->enabled() && !$cache->tryLock($cacheLockKey, max(60, $lockedUntil - $now))) {
                $db->query($db->update('table.mail_queue')->rows([
                    'status' => 'pending',
                    'lockedUntil' => 0,
                    'updated' => $now
                ])->where('id = ? AND status = ? AND lockedUntil = ?', $id, 'processing', $lockedUntil));
                continue;
            }

            $ok = false;
            $err = '';

            try {
                $payload = json_decode((string) $row['payload'], true);
                if (!is_array($payload)) {
                    throw new \RuntimeException('Invalid payload');
                }

                $msg = self::buildMessage(
                    $options,
                    (string) ($payload['to'] ?? ''),
                    (string) ($payload['subject'] ?? ''),
                    (string) ($payload['html'] ?? ''),
                    (string) ($payload['toName'] ?? ''),
                    (string) ($payload['text'] ?? '')
                );

                $result = self::sendMessage($msg, $options);
                if ($result === true) {
                    $ok = true;
                } else {
                    $err = (string) $result;
                }
            } catch (\Throwable $e) {
                $err = $e->getMessage();
            }

            if ($ok) {
                $sent++;
                $db->query($db->update('table.mail_queue')->rows([
                    'status' => 'sent',
                    'lockedUntil' => 0,
                    'attempts' => $attempts + 1,
                    'lastError' => '',
                    'updated' => time()
                ])->where('id = ? AND status = ? AND lockedUntil = ?', $id, 'processing', $lockedUntil));
            } else {
                $failed++;
                $errors[] = ['id' => $id, 'error' => $err];
                $nextAttempts = $attempts + 1;
                $isDead = $nextAttempts >= $maxAttempts;
                $truncatedErr = mb_substr($err, 0, 500, 'UTF-8');
                $db->query($db->update('table.mail_queue')->rows([
                    'status' => $isDead ? 'dead' : 'failed',
                    'lockedUntil' => 0,
                    'attempts' => $nextAttempts,
                    'sendAt' => $isDead ? $now : ($now + self::retryDelay($nextAttempts)),
                    'lastError' => $truncatedErr,
                    'updated' => time()
                ])->where('id = ? AND status = ? AND lockedUntil = ?', $id, 'processing', $lockedUntil));
            }

            if ($cache->enabled()) {
                $cache->delete($cacheLockKey);
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors
        ];
    }

    public static function stats(Db $db): array
    {
        try {
            $pending = (int) ($db->fetchRow($db->select('COUNT(*) AS c')->from('table.mail_queue')->where('status = ?', 'pending'))['c'] ?? 0);
            $failed = (int) ($db->fetchRow($db->select('COUNT(*) AS c')->from('table.mail_queue')->where('status = ?', 'failed'))['c'] ?? 0);
            $dead = (int) ($db->fetchRow($db->select('COUNT(*) AS c')->from('table.mail_queue')->where('status = ?', 'dead'))['c'] ?? 0);
            $sent = (int) ($db->fetchRow($db->select('COUNT(*) AS c')->from('table.mail_queue')->where('status = ?', 'sent'))['c'] ?? 0);

            $lastFail = $db->fetchRow(
                $db->select('id', 'lastError', 'updated')->from('table.mail_queue')->where('status = ?', 'failed')->order('updated', Db::SORT_DESC)->limit(1)
            );
        } catch (\Throwable $e) {
            return ['pending' => 0, 'failed' => 0, 'dead' => 0, 'sent' => 0, 'lastFail' => null, 'recentFails' => []];
        }

        return [
            'pending' => $pending,
            'failed' => $failed,
            'dead' => $dead,
            'sent' => $sent,
            'lastFail' => $lastFail ?: null,
            'recentFails' => self::recentFails($db, 8)
        ];
    }

    public static function maybeCleanup(Db $db, int $keepDays): int
    {
        if ($keepDays <= 0) {
            return 0;
        }

        $cache = \Typecho\Cache::getInstance();
        $cacheKey = 'mail:cleanup:last';
        $interval = 3600;
        $now = time();

        if ($cache->enabled()) {
            $hit = false;
            $lastCleanup = (int) $cache->get($cacheKey, $hit);
            if ($hit && ($now - $lastCleanup) < $interval) {
                return 0;
            }
            $cache->set($cacheKey, $now, $interval * 2);
        }

        return self::cleanup($db, $keepDays);
    }

    public static function cleanup(Db $db, int $keepDays): int
    {
        if ($keepDays <= 0) {
            return 0;
        }

        $keepDays = min(365, $keepDays);
        $before = time() - ($keepDays * 86400);
        try {
            $rows = $db->query($db->delete('table.mail_queue')->where('status = ? AND updated < ?', 'sent', $before));
            return (int) $rows;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function retry(Db $db, string $status, int $limit = 100): int
    {
        if (!in_array($status, ['failed', 'dead'], true)) {
            return 0;
        }

        $limit = max(1, min(500, $limit));
        try {
            $rows = $db->fetchAll(
                $db->select('id')->from('table.mail_queue')
                    ->where('status = ?', $status)
                    ->order('id', Db::SORT_ASC)
                    ->limit($limit)
            );
        } catch (\Throwable $e) {
            return 0;
        }

        if (empty($rows)) {
            return 0;
        }

        $count = 0;
        $now = time();
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $ok = $db->query(
                $db->update('table.mail_queue')->rows([
                    'status' => 'pending',
                    'lockedUntil' => 0,
                    'sendAt' => $now,
                    'updated' => $now
                ])->where('id = ? AND status = ?', $id, $status)
            );
            if ($ok) {
                $count++;
            }
        }

        return $count;
    }

    public static function isUnsub(string $email, string $scope, Db $db): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $row = $db->fetchRow(
                $db->select('id')->from('table.mail_unsub')->where('email = ? AND scope = ?', $email, $scope)->limit(1)
            );
        } catch (\Throwable $e) {
            return false;
        }

        return !empty($row);
    }

    public static function unsub(string $email, string $scope, Db $db): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $exists = $db->fetchRow(
                $db->select('id')->from('table.mail_unsub')->where('email = ? AND scope = ?', $email, $scope)->limit(1)
            );
        } catch (\Throwable $e) {
            return false;
        }

        if ($exists) {
            return true;
        }

        try {
            $db->query(
                $db->insert('table.mail_unsub')->rows([
                    'email' => $email,
                    'scope' => $scope,
                    'created' => time()
                ])
            );
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    public static function buildMessage(
        Options $options,
        string $to,
        string $subject,
        string $html,
        string $toName = '',
        string $text = ''
    ): Message {
        $sender = self::senderDefaults($options);

        return new Message($to, $subject, $html, $sender['from'], $sender['fromName'], $toName, $text);
    }

    public static function buildTransport(Options $options): Transport
    {
        return self::transportName($options) === 'mail'
            ? new Native()
            : new Smtp(self::smtpConfig($options));
    }

    private static function sendMessage(Message $message, Options $options): bool|string
    {
        self::normalizeMessageSender($message, $options);

        if (trim($message->to) === '' || !filter_var($message->to, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid recipient email';
        }
        if (trim($message->from) === '' || !filter_var($message->from, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid sender email';
        }

        return self::buildTransport($options)->send($message);
    }

    private static function retryDelay(int $attempt): int
    {
        return match (true) {
            $attempt <= 1 => 60,
            $attempt === 2 => 300,
            $attempt === 3 => 900,
            $attempt === 4 => 1800,
            default => 3600
        };
    }

    private static function dedupeKey(array $job, array $payload): string
    {
        $meta = $payload['meta'] ?? [];
        $base = [
            'type' => (string) ($job['type'] ?? ''),
            'to' => strtolower(trim((string) ($job['to'] ?? ''))),
            'event' => (string) ($meta['event'] ?? ''),
            'coid' => (int) ($meta['coid'] ?? 0),
            'cid' => (int) ($meta['cid'] ?? 0),
            'ownerId' => (int) ($meta['ownerId'] ?? 0)
        ];

        return sha1(json_encode($base, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private static function senderDefaults(Options $options): array
    {
        return [
            'from' => trim((string) ($options->mailFrom ?? $options->mailSmtpUser ?? '')),
            'fromName' => (string) ($options->mailFromName ?? $options->title ?? 'TypeRenew')
        ];
    }

    private static function smtpConfig(Options $options): array
    {
        $encryptedPass = (string) ($options->mailSmtpPass ?? '');
        $decryptedPass = \Utils\Cipher::decrypt($encryptedPass, (string) ($options->secret ?? ''));

        return [
            'host' => (string) ($options->mailSmtpHost ?? ''),
            'port' => (int) ($options->mailSmtpPort ?? 25),
            'user' => (string) ($options->mailSmtpUser ?? ''),
            'pass' => $decryptedPass,
            'secure' => (string) ($options->mailSmtpSecure ?? ''),
            'timeout' => 10
        ];
    }

    private static function transportName(Options $options): string
    {
        return (string) ($options->mailTransport ?? 'smtp');
    }

    private static function normalizeMessageSender(Message $message, Options $options): void
    {
        $sender = self::senderDefaults($options);

        if (trim($message->from) === '') {
            $message->from = $sender['from'];
        }
        if (trim($message->fromName) === '') {
            $message->fromName = $sender['fromName'];
        }
    }

    private static function recentFails(Db $db, int $limit): array
    {
        try {
            $rows = $db->fetchAll(
                $db->select('id', 'type', 'status', 'attempts', 'lastError', 'updated')->from('table.mail_queue')
                    ->where('(status = ? OR status = ?)', 'failed', 'dead')
                    ->order('updated', Db::SORT_DESC)
                    ->limit(max(1, min(50, $limit)))
            );
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    private static function insertQueueRow(Db $db, array $rows): void
    {
        try {
            $db->query($db->insert('table.mail_queue')->rows($rows));
            return;
        } catch (\Throwable $e) {
            if (self::isDuplicateInsertError($e)) {
                return;
            }

            if (!self::isMissingDedupeColumnError($e)) {
                self::recordRuntimeError('insert', $e->getMessage());
                return;
            }
        }

        try {
            $fallbackRows = $rows;
            unset($fallbackRows['dedupeKey']);
            $db->query($db->insert('table.mail_queue')->rows($fallbackRows));
        } catch (\Throwable $e) {
            return;
        }
    }

    private static function isDuplicateInsertError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'duplicate')
            || str_contains($msg, 'unique constraint')
            || str_contains($msg, '1062')
            || str_contains($msg, '23000')
            || str_contains($msg, '23505');
    }

    private static function isMissingDedupeColumnError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'dedupekey')
            && (str_contains($msg, 'unknown column')
                || str_contains($msg, 'no such column')
                || str_contains($msg, 'undefined column'));
    }

    private static function recordRuntimeError(string $scope, string $message): void
    {
        $text = trim($message);
        if ($text === '') {
            return;
        }
        $value = '[' . $scope . '] ' . mb_substr($text, 0, 500, 'UTF-8');
        error_log('TypeRenew.MailQueue ' . $value);
        self::setRuntimeOption('mailRuntimeError', $value);
        self::setRuntimeOption('mailRuntimeErrorAt', (string) time());
    }

    private static function clearRuntimeError(): void
    {
        self::setRuntimeOption('mailRuntimeError', '');
        self::setRuntimeOption('mailRuntimeErrorAt', '0');
    }

    private static function clearRulesRuntimeError(): void
    {
        try {
            $db = Db::get();
            $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'mailRuntimeError')->limit(1));
            $value = trim((string) ($row['value'] ?? ''));
            if (str_starts_with($value, '[rules]')) {
                self::clearRuntimeError();
            }
        } catch (\Throwable $e) {
        }
    }

    private static function setRuntimeOption(string $name, string $value): void
    {
        try {
            $db = Db::get();
            $exists = $db->fetchRow($db->select('name')->from('table.options')->where('name = ?', $name)->limit(1));
            if ($exists) {
                $db->query($db->update('table.options')->rows(['value' => $value])->where('name = ?', $name));
            } else {
                $db->query($db->insert('table.options')->rows([
                    'name' => $name,
                    'user' => 0,
                    'value' => $value
                ]));
            }
        } catch (\Throwable $e) {
        }
    }
}
