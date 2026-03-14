<?php

namespace Typecho\Mail;

class Native implements Transport
{
    public function send(Message $message): bool|string
    {
        $boundary = 'b' . bin2hex(random_bytes(12));
        $text = trim((string) $message->text);
        if ($text === '') {
            $text = trim(strip_tags((string) $message->html));
        }

        $body = [];
        $body[] = '--' . $boundary;
        $body[] = 'Content-Type: text/plain; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: base64';
        $body[] = '';
        $body[] = chunk_split(base64_encode($text), 76, "\r\n");
        $body[] = '--' . $boundary;
        $body[] = 'Content-Type: text/html; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: base64';
        $body[] = '';
        $body[] = chunk_split(base64_encode((string) $message->html), 76, "\r\n");
        $body[] = '--' . $boundary . '--';

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'From: ' . $this->formatAddress($message->from, $message->fromName);

        $to = $this->formatAddress($message->to, $message->toName);
        $subject = $this->encodeHeader($message->subject);
        $content = implode("\r\n", $body);

        $ok = @mail($to, $subject, $content, implode("\r\n", $headers));
        if ($ok) {
            return true;
        }

        $error = error_get_last();
        $msg = 'mail() failed';
        if (is_array($error) && !empty($error['message'])) {
            $msg .= ': ' . $error['message'];
        }
        return $msg;
    }

    private function formatAddress(string $email, string $name): string
    {
        $email = trim(str_replace(["\r", "\n"], '', $email));
        $name = trim(str_replace(["\r", "\n"], '', $name));
        if ($name === '') {
            return $email;
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], '', $value));
        if ($value === '') {
            return '';
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
