<?php

namespace Typecho\Mail;

class Message
{
    public string $text;

    public function __construct(
        public string $to,
        public string $subject,
        public string $html,
        public string $from,
        public string $fromName = '',
        public string $toName = '',
        string $text = ''
    ) {
        $this->text = $text !== '' ? $text : strip_tags($html);
    }
}
