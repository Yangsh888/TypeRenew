<?php

namespace Utils;

use Typecho\Db;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class LoginGuard
{
    public const SCOPE_LOGIN = 'login';
    public const SCOPE_RESET = 'reset';

    private const DEFAULTS = [
        self::SCOPE_LOGIN => [
            'pairWindow' => 900,
            'pairMax' => 5,
            'pairLock' => 900,
            'ipWindow' => 900,
            'ipMax' => 20,
            'ipLock' => 900
        ],
        self::SCOPE_RESET => [
            'pairWindow' => 3600,
            'pairMax' => 5,
            'pairLock' => 3600,
            'ipWindow' => 3600,
            'ipMax' => 10,
            'ipLock' => 3600
        ]
    ];

    public static function retryAfter(Db $db, string $scope, string $ip, string $identity = ''): int
    {
        $scope = self::normalizeScope($scope);
        $keys = self::keys($scope, $ip, $identity);
        if ($keys === []) {
            return 0;
        }

        $now = time();
        $identityHashes = array_column($keys, 'identityHash');

        try {
            $query = $db->select('lockedUntil')
                ->from('table.login_attempts')
                ->where('scope = ?', $scope)
                ->where('ipHash = ?', $keys[0]['ipHash'])
                ->where('lockedUntil > ?', $now);

            if (count($identityHashes) > 1) {
                $query->where('identityHash = ? OR identityHash = ?', $identityHashes[0], $identityHashes[1]);
            } else {
                $query->where('identityHash = ?', $identityHashes[0]);
            }

            $rows = $db->fetchAll($query);
        } catch (\Throwable $throwable) {
            self::report('retryAfter', $throwable);
            return 0;
        }

        $retryAfter = 0;
        foreach ($rows as $row) {
            $retryAfter = max($retryAfter, (int) ($row['lockedUntil'] ?? 0) - $now);
        }

        return max(0, $retryAfter);
    }

    public static function recordFailure(Db $db, string $scope, string $ip, string $identity = ''): void
    {
        $scope = self::normalizeScope($scope);
        $limits = self::limits($scope);

        foreach (self::keys($scope, $ip, $identity) as $key) {
            $prefix = $key['identityHash'] === '' ? 'ip' : 'pair';

            try {
                self::touch(
                    $db,
                    $scope,
                    $key['ipHash'],
                    $key['identityHash'],
                    (int) $limits[$prefix . 'Window'],
                    (int) $limits[$prefix . 'Max'],
                    (int) $limits[$prefix . 'Lock']
                );
            } catch (\Throwable $throwable) {
                self::report('recordFailure', $throwable);
                return;
            }
        }

        self::maybeCleanup($db);
    }

    public static function clear(Db $db, string $scope, string $ip, string $identity = ''): void
    {
        $scope = self::normalizeScope($scope);

        foreach (self::keys($scope, $ip, $identity) as $key) {
            if ($key['identityHash'] === '') {
                continue;
            }

            try {
                $db->query(
                    $db->delete('table.login_attempts')
                        ->where('scope = ?', $scope)
                        ->where('ipHash = ?', $key['ipHash'])
                        ->where('identityHash = ?', $key['identityHash'])
                );
            } catch (\Throwable $throwable) {
                self::report('clear', $throwable);
                return;
            }
        }
    }

    public static function cleanup(Db $db): void
    {
        $ttl = 0;
        foreach (self::DEFAULTS as $scope => $ignored) {
            $limits = self::limits($scope);
            foreach (['pairWindow', 'pairLock', 'ipWindow', 'ipLock'] as $field) {
                $ttl = max($ttl, (int) $limits[$field]);
            }
        }

        $threshold = time() - max(3600, $ttl * 2);

        try {
            $db->query(
                $db->delete('table.login_attempts')
                    ->where('lastAt < ?', $threshold)
                    ->where('lockedUntil < ?', time())
            );
        } catch (\Throwable $throwable) {
            self::report('cleanup', $throwable);
        }
    }

    public static function limits(string $scope): array
    {
        $scope = self::normalizeScope($scope);
        $limits = self::DEFAULTS[$scope];

        if (!defined('__TYPECHO_LOGIN_GUARD__')) {
            return $limits;
        }

        $overrides = __TYPECHO_LOGIN_GUARD__;
        if (!is_array($overrides) || !is_array($overrides[$scope] ?? null)) {
            return $limits;
        }

        foreach ($limits as $field => $value) {
            $candidate = $overrides[$scope][$field] ?? null;
            if (is_numeric($candidate) && (int) $candidate > 0) {
                $limits[$field] = (int) $candidate;
            }
        }

        return $limits;
    }

    private static function touch(
        Db $db,
        string $scope,
        string $ipHash,
        string $identityHash,
        int $window,
        int $max,
        int $lock
    ): void {
        $now = time();
        $row = self::find($db, $scope, $ipHash, $identityHash);

        if ($row === null) {
            try {
                $db->query(
                    $db->insert('table.login_attempts')->rows([
                        'scope' => $scope,
                        'ipHash' => $ipHash,
                        'identityHash' => $identityHash,
                        'failures' => 1,
                        'firstAt' => $now,
                        'lastAt' => $now,
                        'lockedUntil' => $max <= 1 ? $now + $lock : 0
                    ])
                );
                return;
            } catch (\Throwable) {
                $row = self::find($db, $scope, $ipHash, $identityHash);
                if ($row === null) {
                    return;
                }
            }
        }

        $id = (int) ($row['id'] ?? 0);
        $firstAt = (int) ($row['firstAt'] ?? 0);
        $failures = (int) ($row['failures'] ?? 0);

        if ($id <= 0) {
            return;
        }

        if ($firstAt <= 0 || ($now - $firstAt) > $window) {
            $db->query(
                $db->update('table.login_attempts')
                    ->rows([
                        'failures' => 1,
                        'firstAt' => $now,
                        'lastAt' => $now,
                        'lockedUntil' => $max <= 1 ? $now + $lock : 0
                    ])
                    ->where('id = ?', $id)
            );
            return;
        }

        $rows = ['lastAt' => $now];
        if (($failures + 1) >= $max) {
            $rows['lockedUntil'] = $now + $lock;
        }

        $db->query(
            $db->update('table.login_attempts')
                ->rows($rows)
                ->expression('failures', 'failures + 1', false)
                ->where('id = ?', $id)
        );
    }

    private static function find(Db $db, string $scope, string $ipHash, string $identityHash): ?array
    {
        $row = $db->fetchRow(
            $db->select('id', 'failures', 'firstAt', 'lockedUntil')
                ->from('table.login_attempts')
                ->where('scope = ?', $scope)
                ->where('ipHash = ?', $ipHash)
                ->where('identityHash = ?', $identityHash)
                ->limit(1)
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private static function keys(string $scope, string $ip, string $identity): array
    {
        $ip = trim($ip);
        if ($ip === '' || $ip === 'unknown') {
            return [];
        }

        $ipHash = sha1('ip|' . $ip);
        $keys = [['ipHash' => $ipHash, 'identityHash' => '']];

        $identity = trim($identity);
        if ($identity !== '') {
            $keys[] = [
                'ipHash' => $ipHash,
                'identityHash' => sha1('id|' . strtolower($identity))
            ];
        }

        return $keys;
    }

    private static function normalizeScope(string $scope): string
    {
        return isset(self::DEFAULTS[$scope]) ? $scope : self::SCOPE_LOGIN;
    }

    private static function maybeCleanup(Db $db): void
    {
        try {
            if (random_int(1, 20) !== 1) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        self::cleanup($db);
    }

    private static function report(string $stage, \Throwable $throwable): void
    {
        error_log('Utils.LoginGuard.' . $stage . ': ' . $throwable->getMessage());
    }
}
