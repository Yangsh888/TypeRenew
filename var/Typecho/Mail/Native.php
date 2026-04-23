<?php

namespace Typecho\Mail;

class Native implements Transport
{
    public function send(Message $message): bool|string
    {
        $boundary = Mime::boundary();
        $body = Mime::buildAlternativeBody($message, $boundary);

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'From: ' . Mime::formatAddress($message->from, $message->fromName);

        $to = Mime::formatAddress($message->to, $message->toName);
        $subject = Mime::encodeHeader($message->subject);
        $content = implode("\r\n", $body);

        $errorMessage = null;
        set_error_handler(static function (int $severity, string $message) use (&$errorMessage): bool {
            $errorMessage = $message;
            return true;
        });

        try {
            $ok = mail($to, $subject, $content, implode("\r\n", $headers));
        } finally {
            restore_error_handler();
        }

        if ($ok) {
            return true;
        }

        $msg = 'mail() failed';
        if ($errorMessage !== null && $errorMessage !== '') {
            $msg .= ': ' . $errorMessage;
        }
        return $msg;
    }
}
