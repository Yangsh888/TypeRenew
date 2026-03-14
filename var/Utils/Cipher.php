<?php

namespace Utils;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Cipher
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;
    private const MARKER = 'enc:v1:';

    public static function encrypt(string $plaintext, string $secret): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = self::deriveKey($secret);
        $iv = random_bytes(12);

        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

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

        if (!str_starts_with($ciphertext, self::MARKER)) {
            return $ciphertext;
        }

        $data = base64_decode(substr($ciphertext, strlen(self::MARKER)), true);
        if ($data === false || strlen($data) < 28) {
            return '';
        }

        $key = self::deriveKey($secret);
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, self::TAG_LENGTH);
        $encrypted = substr($data, 28);

        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $decrypted === false ? '' : $decrypted;
    }

    public static function isEncrypted(string $value): bool
    {
        return $value !== '' && str_starts_with($value, self::MARKER);
    }

    public static function mask(string $value, int $visibleChars = 4): string
    {
        if ($value === '') {
            return '';
        }

        $length = strlen($value);
        if ($length <= $visibleChars) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, $visibleChars) . str_repeat('*', min($length - $visibleChars, 8));
    }

    private static function deriveKey(string $secret): string
    {
        $salt = 'typerenew:v1:' . hash('sha256', $secret);
        return hash_pbkdf2('sha256', $secret, $salt, 10000, 32, true);
    }
}
