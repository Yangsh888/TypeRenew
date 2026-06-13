<?php

namespace Typecho;

use Typecho\Widget\Terminal;

class Response
{
    private const COOKIE_UNIX_TIMESTAMP_MIN = 946684800;

    private const HTTP_CODE = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    ];

    private static Response $instance;
    private string $charset = 'UTF-8';
    private string $contentType = 'text/html';
    private array $responders = [];
    private array $backgroundResponders = [];
    private array $cookies = [];
    private array $headers = [];
    private int $status = 200;
    private bool $enableAutoSendHeaders = true;
    private bool $sandbox = false;
    private bool $finished = false;

    public function __construct()
    {
        $this->clean();
    }

    public static function getInstance(): Response
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function beginSandbox(): Response
    {
        $this->sandbox = true;
        return $this;
    }

    public function endSandbox(): Response
    {
        $this->sandbox = false;
        return $this;
    }

    public function enableAutoSendHeaders(bool $enable = true)
    {
        $this->enableAutoSendHeaders = $enable;
    }

    public function clean()
    {
        $this->headers = [];
        $this->cookies = [];
        $this->status = 200;
        $this->responders = [];
        $this->backgroundResponders = [];
        $this->finished = false;
        $this->setContentType('text/html');
    }

    public function sendHeaders()
    {
        if ($this->sandbox) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        $sentHeaders = [];
        foreach (headers_list() as $header) {
            [$key] = explode(':', $header, 2);
            $sentHeaders[] = strtolower(trim($key));
        }

        header('HTTP/1.1 ' . $this->status . ' ' . (self::HTTP_CODE[$this->status] ?? ''), true, $this->status);

        foreach ($this->headers as $name => $value) {
            if (!in_array(strtolower($name), $sentHeaders)) {
                header($name . ': ' . $value);
            }
        }

        foreach ($this->cookies as $cookie) {
            [$key, $value, $timeout, $path, $domain, $secure, $httponly, $sameSite] = $cookie;

            if ($timeout < 0) {
                $timeout = 1;
            }

            setrawcookie($key, rawurlencode($value), [
                'expires' => $timeout,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $sameSite
            ]);
        }
    }

    public function respond()
    {
        if ($this->sandbox) {
            throw new Terminal('sandbox mode');
        }

        if ($this->enableAutoSendHeaders) {
            $this->sendHeaders();
        }

        foreach ($this->responders as $responder) {
            $responder($this);
        }

        if (!empty($this->backgroundResponders)) {
            $this->finish(true);

            foreach ($this->backgroundResponders as $responder) {
                try {
                    $responder($this);
                } catch (\Throwable $e) {
                    error_log('Response.background: ' . $e->getMessage());
                }
            }
        }

        exit;
    }

    public function finish(bool $preserveBody = false): void
    {
        if ($this->sandbox || $this->finished) {
            return;
        }

        $isFastCGI = function_exists('fastcgi_finish_request');

        if (!$isFastCGI) {
            if ($preserveBody) {
                if (ob_get_level() <= 0) {
                    ob_start();
                }

                $length = ob_get_length();
                $this->setHeader('Connection', 'close');
                $this->setHeader('Content-Length', (string) max(0, (int) ($length === false ? 0 : $length)));
            } else {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                ob_start();
                $this->setHeader('Connection', 'close');
                $this->setHeader('Content-Length', '0');
            }
        }

        $this->sendHeaders();
        $this->finished = true;

        if ($isFastCGI) {
            fastcgi_finish_request();
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        flush();
    }

    public function setStatus(int $code): Response
    {
        if ($this->sandbox) {
            return $this;
        }

        $this->status = $code >= 100 && $code < 600 ? $code : 500;
        return $this;
    }

    public function setHeader(string $name, string $value): Response
    {
        if ($this->sandbox) {
            return $this;
        }

        $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
        $this->headers[$name] = $value;
        return $this;
    }

    public function setCookie(
        string $key,
        $value,
        int $timeout = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = false,
        string $sameSite = 'Lax'
    ): Response {
        if ($this->sandbox) {
            return $this;
        }

        $sameSite = ucfirst(strtolower(trim($sameSite)));
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }
        if ($sameSite === 'None' && !$secure) {
            $sameSite = 'Lax';
        }
        $this->cookies[] = [
            $key,
            $value,
            $this->normalizeCookieExpires($timeout),
            $path,
            $domain,
            $secure,
            $httponly,
            $sameSite
        ];
        return $this;
    }

    private function normalizeCookieExpires(int $timeout): int
    {
        if ($timeout <= 0) {
            return $timeout;
        }

        if ($timeout < self::COOKIE_UNIX_TIMESTAMP_MIN) {
            return time() + $timeout;
        }

        return $timeout;
    }

    public function setContentType(string $contentType): Response
    {
        if ($this->sandbox) {
            return $this;
        }

        $this->contentType = $contentType;
        $this->setHeader('Content-Type', $this->contentType . '; charset=' . $this->charset);
        return $this;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function setCharset(string $charset): Response
    {
        if ($this->sandbox) {
            return $this;
        }

        $this->charset = $charset;
        $this->setHeader('Content-Type', $this->contentType . '; charset=' . $this->charset);
        return $this;
    }

    public function addResponder(callable $responder): Response
    {
        if ($this->sandbox) {
            return $this;
        }

        $this->responders[] = $responder;
        return $this;
    }

    public function addBackgroundResponder(callable $responder): Response
    {
        if ($this->sandbox) {
            return $this;
        }

        $this->backgroundResponders[] = $responder;
        return $this;
    }
}
