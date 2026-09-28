<?php

namespace Utils;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Cipher
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;
    private const MARKER = 'enc:v2:';
    private const LEGACY_MARKER = 'enc:v1:';

    public static function encrypt(string $plaintext, string $secret): string
    {
        if ($plaintext === '') {
            return '';
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, self::key($secret), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        if ($ciphertext === false) {
            return '';
        }

        return self::MARKER . base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $ciphertext, string $secret): string
    {
        if ($ciphertext === '') {
            return '';
        }

        if (str_starts_with($ciphertext, self::MARKER)) {
            $key = self::key($secret);
        } elseif (str_starts_with($ciphertext, self::LEGACY_MARKER)) {
            $key = self::legacyKey($secret);
        } else {
            return $ciphertext;
        }

        $data = base64_decode(substr($ciphertext, strlen(self::MARKER)), true);
        if ($data === false || strlen($data) < 28) {
            return '';
        }

        $decrypted = openssl_decrypt(substr($data, 28), self::CIPHER, $key, OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, self::TAG_LENGTH));

        return $decrypted === false ? '' : $decrypted;
    }

    private static function key(string $secret): string
    {
        return hash_hkdf('sha256', $secret, 32, 'typerenew:cipher:v2');
    }

    private static function legacyKey(string $secret): string
    {
        static $keys = [];

        $cacheKey = hash('sha256', $secret);

        return $keys[$cacheKey] ??= hash_pbkdf2('sha256', $secret, 'typerenew:v1:' . $cacheKey, 60000, 32, true);
    }
}
