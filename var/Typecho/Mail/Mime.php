<?php

namespace Typecho\Mail;

class Mime
{
    public static function boundary(): string
    {
        return 'b' . bin2hex(random_bytes(12));
    }

    public static function formatAddress(string $email, string $name): string
    {
        $email = trim(str_replace(["\r", "\n"], '', $email));
        $name = trim(str_replace(["\r", "\n"], '', $name));
        if ($name === '') {
            return $email;
        }

        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    public static function encodeHeader(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], '', $value));
        if ($value === '') {
            return '';
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    public static function buildAlternativeBody(Message $message, string $boundary): array
    {
        return [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode(self::plainText($message)), 76, "\r\n"),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode((string) $message->html), 76, "\r\n"),
            '--' . $boundary . '--'
        ];
    }

    private static function plainText(Message $message): string
    {
        $text = trim((string) $message->text);
        if ($text !== '') {
            return $text;
        }

        return trim(strip_tags((string) $message->html));
    }
}
