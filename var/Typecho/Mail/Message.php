<?php

namespace Typecho\Mail;

class Message
{
    public string $to;
    public string $toName;
    public string $from;
    public string $fromName;
    public string $subject;
    public string $html;
    public string $text;

    public function __construct(
        string $to,
        string $subject,
        string $html,
        string $from,
        string $fromName = '',
        string $toName = '',
        string $text = ''
    ) {
        $this->to = $to;
        $this->toName = $toName;
        $this->from = $from;
        $this->fromName = $fromName;
        $this->subject = $subject;
        $this->html = $html;
        $this->text = $text !== '' ? $text : strip_tags($html);
    }
}
