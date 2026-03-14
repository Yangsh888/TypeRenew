<?php

namespace Typecho\Mail;

class Smtp implements Transport
{
    private array $config;
    private $socket = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(Message $message): bool|string
    {
        $host = (string) ($this->config['host'] ?? '');
        $port = (int) ($this->config['port'] ?? 25);
        $secure = (string) ($this->config['secure'] ?? '');
        $timeout = (int) ($this->config['timeout'] ?? 10);

        if ($host === '' || $port <= 0) {
            return 'Invalid SMTP host/port';
        }

        $remote = $secure === 'ssl' ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ]);

        $this->socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            return 'SMTP connect failed: ' . ($errstr ?: (string) $errno);
        }

        stream_set_timeout($this->socket, $timeout);
        $greeting = $this->readResponse();
        if (!$this->isPositive($greeting)) {
            $this->close();
            return 'SMTP greeting failed: ' . $greeting;
        }

        $ehlo = $this->command('EHLO typerenew');
        if (!$this->isPositive($ehlo)) {
            $helo = $this->command('HELO typerenew');
            if (!$this->isPositive($helo)) {
                $this->close();
                return 'SMTP HELO/EHLO failed: ' . $helo;
            }
        }

        if ($secure === 'tls') {
            $startTls = $this->command('STARTTLS');
            if (!$this->isPositive($startTls)) {
                $this->close();
                return 'SMTP STARTTLS failed: ' . $startTls;
            }

            $cryptoOk = @stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) {
                $this->close();
                return 'TLS handshake failed';
            }

            $ehlo = $this->command('EHLO typerenew');
            if (!$this->isPositive($ehlo)) {
                $this->close();
                return 'SMTP EHLO after STARTTLS failed: ' . $ehlo;
            }
        }

        $user = (string) ($this->config['user'] ?? '');
        $pass = (string) ($this->config['pass'] ?? '');
        if ($user !== '') {
            $authResp = $this->authenticate($user, $pass, $ehlo);
            if ($authResp !== true) {
                $this->close();
                return (string) $authResp;
            }
        }

        $from = $message->from;
        $to = $message->to;

        $mailFrom = $this->command('MAIL FROM:<' . $from . '>');
        if (!$this->isPositive($mailFrom)) {
            $this->close();
            return 'MAIL FROM failed: ' . $mailFrom;
        }

        $rcptTo = $this->command('RCPT TO:<' . $to . '>');
        if (!$this->isPositive($rcptTo)) {
            $this->close();
            return 'RCPT TO failed: ' . $rcptTo;
        }

        $data = $this->command('DATA');
        if (!$this->isPositive($data)) {
            $this->close();
            return 'DATA failed: ' . $data;
        }

        $raw = $this->buildMime($message);
        $raw = str_replace(["\r\n.\r\n", "\n.\n"], ["\r\n..\r\n", "\n..\n"], $raw);
        $this->write($raw . "\r\n.\r\n");
        $result = $this->readResponse();
        if (!$this->isPositive($result)) {
            $this->close();
            return 'SMTP body rejected: ' . $result;
        }

        $this->command('QUIT');
        $this->close();
        return true;
    }

    private function buildMime(Message $message): string
    {
        $boundary = 'b' . bin2hex(random_bytes(12));
        $headers = [];
        $headers[] = 'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000';
        $headers[] = 'From: ' . $this->formatAddress($message->from, $message->fromName);
        $headers[] = 'To: ' . $this->formatAddress($message->to, $message->toName);
        $headers[] = 'Subject: ' . $this->encodeHeader($message->subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = [];
        $body[] = '--' . $boundary;
        $body[] = 'Content-Type: text/plain; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: base64';
        $body[] = '';
        $body[] = chunk_split(base64_encode($message->text), 76, "\r\n");

        $body[] = '--' . $boundary;
        $body[] = 'Content-Type: text/html; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: base64';
        $body[] = '';
        $body[] = chunk_split(base64_encode($message->html), 76, "\r\n");

        $body[] = '--' . $boundary . '--';

        return implode("\r\n", array_merge($headers, [''], $body));
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

    private function command(string $line): string
    {
        $this->write($line . "\r\n");
        return $this->readResponse();
    }

    private function write(string $data): void
    {
        if ($this->socket) {
            fwrite($this->socket, $data);
        }
    }

    private function readResponse(): string
    {
        if (!$this->socket) {
            return '';
        }

        $lines = [];
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 515);
            if ($line === false) {
                break;
            }

            $lines[] = rtrim($line, "\r\n");
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }

        return implode("\n", $lines);
    }

    private function isPositive(string $resp): bool
    {
        return preg_match('/^(2|3)\d{2}/', $resp) === 1;
    }

    private function authenticate(string $user, string $pass, string $ehlo): bool|string
    {
        $supportsPlain = $this->supportsAuth($ehlo, 'PLAIN');
        $supportsLogin = $this->supportsAuth($ehlo, 'LOGIN');

        if ($supportsPlain) {
            $token = base64_encode("\0" . $user . "\0" . $pass);
            $resp = $this->command('AUTH PLAIN ' . $token);
            if ($this->isPositive($resp)) {
                return true;
            }
        }

        if ($supportsLogin || (!$supportsPlain && !$supportsLogin)) {
            $auth = $this->command('AUTH LOGIN');
            if ($this->isPositive($auth)) {
                $u = $this->command(base64_encode($user));
                if (!$this->isPositive($u)) {
                    return 'SMTP username rejected: ' . $u;
                }

                $p = $this->command(base64_encode($pass));
                if ($this->isPositive($p)) {
                    return true;
                }

                return 'SMTP password rejected: ' . $p;
            }
        }

        return 'SMTP AUTH not accepted';
    }

    private function supportsAuth(string $ehlo, string $method): bool
    {
        if ($ehlo === '') {
            return false;
        }

        $method = strtoupper($method);
        if (!preg_match('/AUTH(?:=|\\s+)([^\\n\\r]+)/i', $ehlo, $matches)) {
            return false;
        }

        $caps = strtoupper((string) ($matches[1] ?? ''));
        return str_contains($caps, $method);
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}
