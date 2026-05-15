<?php

namespace Utils;

use Typecho\Db;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class PasswordReset
{
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function isValidRawToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findActiveRecordByToken(Db $db, string $rawToken): ?array
    {
        if (!self::isValidRawToken($rawToken)) {
            return null;
        }

        $now = time();
        $tokenHash = self::hashToken($rawToken);

        $record = $db->fetchRow(
            $db->select()->from('table.password_resets')
                ->where('token = ?', $tokenHash)
                ->where('used = ?', 0)
                ->where('expires > ?', $now)
                ->limit(1)
        );

        if ($record) {
            return $record;
        }

        // 兼容旧版本使用 bcrypt 保存的临时重置链接，避免升级瞬间导致已发邮件全部失效。
        $legacyRecords = $db->fetchAll(
            $db->select()->from('table.password_resets')
                ->where('token LIKE ?', '$2%')
                ->where('used = ?', 0)
                ->where('expires > ?', $now)
        );

        foreach ($legacyRecords as $legacyRecord) {
            if (password_verify($rawToken, (string) ($legacyRecord['token'] ?? ''))) {
                return $legacyRecord;
            }
        }

        return null;
    }
}
