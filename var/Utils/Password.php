<?php

namespace Utils;

use Typecho\Common;

class Password
{
    private const BCRYPT_COST = 12;
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 72;

    public static function hash(string $password): string
    {
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
        $length = Common::strLen($password);
        return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH;
    }

    public static function verify(string $password, string $hash): bool
    {
        if (self::isModernHash($hash)) {
            return password_verify($password, $hash);
        }

        if (!self::allowLegacy()) {
            return false;
        }

        return self::verifyLegacy($password, $hash);
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
            $computed = self::verifyPhpass($password, $hash);
            return hash_equals($hash, $computed);
        }

        if (strpos($hash, '$T$') === 0) {
            return Common::hashValidate($password, $hash);
        }

        if (!self::allowMd5Legacy()) {
            return false;
        }

        $computed = md5($password);
        return hash_equals($hash, $computed);
    }

    private static function verifyPhpass(string $password, string $hash): bool
    {
        return self::cryptPrivate($password, $hash) === $hash;
    }

    private static function cryptPrivate(string $password, string $setting): string
    {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $output = '*0';

        if (substr($setting, 0, 2) === $output) {
            $output = '*1';
        }

        if (substr($setting, 0, 3) !== '$P$') {
            return $output;
        }

        $countLog2 = strpos($itoa64, $setting[3]);

        if ($countLog2 < 7 || $countLog2 > 30) {
            return $output;
        }

        $count = 1 << $countLog2;
        $salt = substr($setting, 4, 8);

        if (strlen($salt) !== 8) {
            return $output;
        }

        $hash = md5($salt . $password, true);

        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        $output = substr($setting, 0, 12);
        $output .= self::encode64($hash, 16);

        return $output;
    }

    private static function encode64(string $input, int $count): string
    {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $output = '';
        $i = 0;

        do {
            $value = ord($input[$i++]);
            $output .= $itoa64[$value & 0x3f];

            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }

            $output .= $itoa64[($value >> 6) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }

            $output .= $itoa64[($value >> 12) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            $output .= $itoa64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }
}
