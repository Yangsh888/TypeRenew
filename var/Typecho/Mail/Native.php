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

}
