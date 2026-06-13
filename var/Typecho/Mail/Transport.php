<?php

namespace Typecho\Mail;

interface Transport
{
    public function send(Message $message): bool|string;

    public function open(): void;

    public function close(): void;
}
