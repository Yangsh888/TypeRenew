<?php

namespace Typecho\Http;

use Typecho\Common;
use Typecho\Http\Client\Exception;

class Client
{
    public const METHOD_POST = 'POST';

    public const METHOD_GET = 'GET';

    public const METHOD_PUT = 'PUT';

    public const METHOD_DELETE = 'DELETE';

    private string $method = self::METHOD_GET;

    public string $agent;

    private string $query = '';

    private int $timeout = 3;

    private bool $multipart = true;

    private array|string $data = [];

    private array $headers = [];

    private array $cookies = [];

    private array $options = [];

    private array $responseHeader = [];

    private int $responseStatus = 0;

    private string $responseBody = '';

    private string $responseUrl = '';

    private bool $useCurl = true;

    public function setCookie(string $key, $value): Client
    {
        $this->cookies[$key] = $value;
        $this->setHeader('Cookie', str_replace('&', '; ', http_build_query($this->cookies)));
        return $this;
    }

    public function setQuery($query): Client
    {
        $query = is_array($query) ? http_build_query($query) : (string) $query;
        $this->query = $this->query === '' ? $query : $this->query . '&' . $query;
        return $this;
    }

    public function setData($data, string $method = self::METHOD_POST): Client
    {
        if (is_array($data) && is_array($this->data)) {
            $this->data = array_merge($this->data, $data);
        } else {
            $this->data = $data;
        }

        $this->setMethod($method);
        return $this;
    }

    public function setJson($data, string $method = self::METHOD_POST): Client
    {
        $this->setData(json_encode($data), $method)
            ->setMultipart(true)
            ->setHeader('Content-Type', 'application/json');

        return $this;
    }

    public function setMethod(string $method): Client
    {
        $this->method = $method;
        return $this;
    }

    public function setFiles(array $files, string $method = self::METHOD_POST): Client
    {
        if (is_array($this->data)) {
            foreach ($files as $name => $file) {
                $this->data[$name] = new \CURLFile($file);
            }
        }

        $this->setMethod($method);
        return $this;
    }

    public function setTimeout(int $timeout): Client
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function setAgent(string $agent): Client
    {
        $this->agent = $agent;
        return $this;
    }

    public function setMultipart(bool $multipart): Client
    {
        $this->multipart = $multipart;
        return $this;
    }

    public function setOption(int $key, $value): Client
    {
        $this->options[$key] = $value;
        return $this;
    }

    public function setHeader(string $key, string $value): Client
    {
        $key = str_replace(' ', '-', ucwords(str_replace('-', ' ', $key)));

        if ($key == 'User-Agent') {
            $this->setAgent($value);
        } else {
            $this->headers[$key] = $value;
        }

        return $this;
    }

    public function send(string $url)
    {
        $params = Common::parseUrl($url);
        if ($params === []) {
            throw new Exception('Invalid request url');
        }
        $query = $params['query'] ?? '';

        if ($this->query !== '') {
            $query = $query === '' ? $this->query : $query . '&' . $this->query;
        }

        if ($query !== '') {
            $params['query'] = $query;
        }

        $url = Common::buildUrl($params);
        if (!$this->useCurl) {
            $this->sendWithStreams($url);
            return;
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if (isset($this->agent)) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->agent);
        }

        if (!empty($this->headers)) {
            $headers = [];

            foreach ($this->headers as $key => $val) {
                $headers[] = $key . ': ' . $val;
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if (!empty($this->data)) {
            $data = $this->data;

            if (!$this->multipart) {
                curl_setopt($ch, CURLOPT_POST, true);
                $data = is_array($data) ? http_build_query($data) : $data;
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) {
            $parts = explode(':', $header, 2);

            if (count($parts) == 2) {
                [$key, $value] = $parts;
                $this->responseHeader[strtolower(trim($key))] = trim($value);
            }

            return strlen($header);
        });

        foreach ($this->options as $key => $val) {
            curl_setopt($ch, $key, $val);
        }

        $response = curl_exec($ch);
        if (false === $response) {
            $error = curl_error($ch);
            unset($ch);
            throw new Exception($error, 500);
        }

        $this->responseStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->responseUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $this->responseBody = $response;
        unset($ch);
    }

    private function sendWithStreams(string $url): void
    {
        $headers = [];

        foreach ($this->headers as $key => $val) {
            $headers[] = $key . ': ' . $val;
        }

        if (isset($this->agent)) {
            $headers[] = 'User-Agent: ' . $this->agent;
        }

        $content = null;
        if (!empty($this->data)) {
            $content = $this->data;
            if (is_array($content)) {
                $content = is_array($content) ? http_build_query($content) : $content;
                if (!isset($this->headers['Content-Type'])) {
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                }
            } elseif (!$this->multipart) {
                $content = is_array($content) ? http_build_query($content) : $content;
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => $this->method,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'follow_location' => 0,
                'protocol_version' => 1.1,
                'header' => implode("\r\n", $headers),
                'content' => $content,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $error = error_get_last();
            throw new Exception($error['message'] ?? 'Stream request failed', 500);
        }

        $this->responseHeader = [];
        $this->responseStatus = 200;
        $responseHeaders = is_array($http_response_header ?? null) ? $http_response_header : [];
        foreach ($responseHeaders as $index => $header) {
            if ($index === 0) {
                if (preg_match('#\s(\d{3})(?:\s|$)#', $header, $matches)) {
                    $this->responseStatus = (int) $matches[1];
                }
                continue;
            }

            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                [$key, $value] = $parts;
                $this->responseHeader[strtolower(trim($key))] = trim($value);
            }
        }

        $this->responseUrl = $url;
        $this->responseBody = $response;
    }

    private static function canUseStreams(): bool
    {
        $allowUrlFopen = strtolower((string) ini_get('allow_url_fopen'));
        if ($allowUrlFopen === '0' || $allowUrlFopen === 'off') {
            return false;
        }

        $wrappers = stream_get_wrappers();
        return in_array('http', $wrappers, true) || in_array('https', $wrappers, true);
    }

    public function getResponseHeader(string $key): ?string
    {
        $key = strtolower($key);
        return $this->responseHeader[$key] ?? null;
    }

    public function getResponseStatus(): int
    {
        return $this->responseStatus;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    public function getResponseUrl(): string
    {
        return $this->responseUrl;
    }

    public static function get(): ?Client
    {
        $client = new static();
        $client->useCurl = extension_loaded('curl')
            && function_exists('curl_init');

        if (!$client->useCurl && !self::canUseStreams()) {
            return null;
        }

        return $client;
    }
}
