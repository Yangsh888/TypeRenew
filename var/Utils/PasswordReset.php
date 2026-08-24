<?php

namespace Utils;

use Typecho\Db;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class PasswordReset
{
    public static function cleanupExpired(Db $db): void
    {
        $db->query(
            $db->delete('table.password_resets')
                ->where('expires < ?', time())
        );
    }

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

    public static function apply(Db $db, string $rawToken, string $passwordHash, string $authCode): bool
    {
        $db->query('BEGIN');

        try {
            $record = self::findActiveRecordByToken($db, $rawToken);
            if (!$record) {
                $db->query('ROLLBACK');
                return false;
            }

            $recordId = (int) ($record['id'] ?? 0);
            $email = (string) ($record['email'] ?? '');
            $locked = $db->query(
                $db->update('table.password_resets')
                    ->rows(['used' => 1])
                    ->where('id = ? AND used = ? AND expires > ?', $recordId, 0, time())
            );

            if ($locked !== 1) {
                $db->query('ROLLBACK');
                return false;
            }

            $updated = $db->query(
                $db->update('table.users')
                    ->rows([
                        'password' => $passwordHash,
                        'authCode' => $authCode,
                    ])
                    ->where('mail = ?', $email)
            );

            if ($updated !== 1) {
                throw new \RuntimeException(_t('密码对应的用户不存在'));
            }

            $db->query(
                $db->delete('table.password_resets')
                    ->where('email = ?', $email)
                    ->where('id <> ?', $recordId)
            );
            $db->query('COMMIT');
            return true;
        } catch (\Throwable $throwable) {
            try {
                $db->query('ROLLBACK');
            } catch (\Throwable) {
            }

            throw $throwable;
        }
    }
}
