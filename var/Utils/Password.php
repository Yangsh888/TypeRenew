<?php

namespace Utils;

use Typecho\Common;

class Password
{
    private const BCRYPT_COST = 12;
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 72;

    public static function hash(string $password): string
    {
        if (str_contains($password, "\0")) {
            throw new \InvalidArgumentException('Password must not contain a null byte');
        }

        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    public static function minLength(): int
    {
        return self::MIN_LENGTH;
    }

    public static function maxLength(): int
    {
        return self::MAX_LENGTH;
    }

    public static function validateLength(string $password): bool
    {
        if (str_contains($password, "\0")) {
            return false;
        }

        $length = Common::strLen($password);
        return $length >= self::MIN_LENGTH
            && $length <= self::MAX_LENGTH
            && strlen($password) <= self::MAX_LENGTH;
    }

    public static function verify(string $password, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            return false;
        }

        if (self::isModernHash($hash)) {
            return password_verify($password, $hash);
        }

        if (!self::allowLegacy()) {
            return false;
        }

        return self::verifyLegacy($password, $hash);
    }

    public static function hashPassword(string $password): string
    {
        return self::hash($password);
    }

    public static function checkPassword(string $password, ?string $hash): bool
    {
        return self::verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        if (self::isModernHash($hash)) {
            return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
        }

        return true;
    }

    private static function isModernHash(string $hash): bool
    {
        return (password_get_info($hash)['algo'] ?? 0) !== 0;
    }

    private static function allowLegacy(): bool
    {
        return !defined('__TYPECHO_DISABLE_LEGACY_PASSWORD__') || !__TYPECHO_DISABLE_LEGACY_PASSWORD__;
    }

    private static function allowMd5Legacy(): bool
    {
        return defined('__TYPECHO_ALLOW_MD5_PASSWORD__') && __TYPECHO_ALLOW_MD5_PASSWORD__;
    }

    private static function verifyLegacy(string $password, string $hash): bool
    {
        if (strpos($hash, '$P$') === 0) {
            $computed = self::phpass($password, $hash);
            return $computed !== '' && hash_equals($hash, $computed);
        }

        if (strpos($hash, '$T$') === 0) {
            if (strpos($password, "\0") !== false) {
                return false;
            }

            return Common::hashValidate($password, $hash);
        }

        if (!self::allowMd5Legacy()) {
            return false;
        }

        $computed = md5($password);
        return hash_equals($hash, $computed);
    }

    private static function phpass(string $password, string $setting): string
    {
        $salt = substr($setting, 4, 8);

        if (strlen($salt) !== 8) {
            return '';
        }

        $countLog2 = strpos(self::ITOA64, $setting[3] ?? '');

        if ($countLog2 === false || $countLog2 < 7 || $countLog2 > 30) {
            return '';
        }

        $count = 1 << $countLog2;
        $digest = md5($salt . $password, true);

        do {
            $digest = md5($digest . $password, true);
        } while (--$count);

        return substr($setting, 0, 12) . self::encode64($digest);
    }

    private static function encode64(string $input): string
    {
        $count = strlen($input);
        $output = '';
        $i = 0;

        do {
            $value = ord($input[$i++]);
            $output .= self::ITOA64[$value & 0x3f];

            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }

            $output .= self::ITOA64[($value >> 6) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }

            $output .= self::ITOA64[($value >> 12) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            $output .= self::ITOA64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }
}
