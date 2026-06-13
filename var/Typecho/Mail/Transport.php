<?php

namespace Typecho\Mail;

interface Transport
{
    public function send(Message $message): bool|string;

    /**
     * 开启批量会话: 实现可在此建立并保持连接, 供同一批多封邮件复用。
     * 无连接概念的实现 (如 mail()) 可空实现。
     */
    public function open(): void;

    /**
     * 关闭批量会话并释放连接。与 open() 成对调用。
     */
    public function close(): void;
}
