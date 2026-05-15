<?php

namespace Typecho\Widget;

use Typecho\Common;
use Typecho\Request as HttpRequest;
use Typecho\Response as HttpResponse;

class Response
{
    private HttpRequest $request;
    private HttpResponse $response;

    public function __construct(HttpRequest $request, HttpResponse $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function setStatus(int $code): Response
    {
        $this->response->setStatus($code);
        return $this;
    }

    public function setHeader(string $name, $value): Response
    {
        $this->response->setHeader($name, (string)$value);
        return $this;
    }

    public function setCharset(string $charset): Response
    {
        $this->response->setCharset($charset);
        return $this;
    }

    public function setContentType(string $contentType = 'text/html'): Response
    {
        $this->response->setContentType($contentType);
        return $this;
    }

    public function throwCallback(callable $callback, string $contentType = 'text/html')
    {
        $this->response->setContentType($contentType)
            ->addResponder($callback)
            ->respond();
    }

    public function throwFinish()
    {
        $this->response->finish();
    }

    public function throwContent(string $content, string $contentType = 'text/html')
    {
        $this->throwCallback(function () use ($content) {
            echo $content;
        }, $contentType);
    }

    public function throwXml($message)
    {
        $this->throwCallback(function () use ($message) {
            echo '<?xml version="1.0" encoding="' . $this->response->getCharset() . '"?>',
            '<response>',
            $this->parseXml($message),
            '</response>';
        }, 'text/xml');
    }

    public function throwJson($message)
    {
        $this->throwCallback(function () use ($message) {
            echo Common::jsonEncode($message);
        }, 'application/json');
    }

    public function throwFile($file, ?string $contentType = null)
    {
        if (!empty($contentType)) {
            $this->response->setContentType($contentType);
        }

        $this->response->setHeader('Content-Length', filesize($file))
            ->addResponder(function () use ($file) {
                readfile($file);
            })
            ->respond();
    }

    public function redirect(string $location, bool $isPermanently = false)
    {
        $location = Common::safeUrl($location);

        $this->response->setStatus($isPermanently ? 301 : 302)
            ->setHeader('Location', $location)
            ->respond();
    }

    public function goBack(?string $suffix = null, ?string $default = null)
    {
        $referer = $this->request->getReferer();

        if (!empty($referer) && $this->isSafeReferer($referer)) {
            if (!empty($suffix)) {
                $parts = Common::parseUrl($referer);
                $myParts = Common::parseUrl($suffix);

                if ($parts !== [] && isset($myParts['fragment'])) {
                    $parts['fragment'] = $myParts['fragment'];
                }

                if ($parts !== [] && isset($myParts['query'])) {
                    $args = [];
                    if (isset($parts['query'])) {
                        parse_str($parts['query'], $args);
                    }

                    parse_str($myParts['query'], $currentArgs);
                    $args = array_merge($args, $currentArgs);
                    $parts['query'] = http_build_query($args);
                }

                if ($parts !== []) {
                    $referer = Common::buildUrl($parts);
                }
            }

            $this->redirect($referer);
        } else {
            $this->redirect($default ?: '/');
        }
    }

    private function isSafeReferer(string $referer): bool
    {
        $parts = Common::parseUrl($referer);
        if ($parts === []) {
            return false;
        }

        if (!isset($parts['host']) && !isset($parts['scheme'])) {
            return isset($parts['path']) && str_starts_with((string) $parts['path'], '/');
        }

        $current = Common::parseUrl($this->request->getRequestUrl());
        if ($current === [] || empty($current['host']) || empty($current['scheme'])) {
            return false;
        }

        $currentHost = strtolower((string) ($current['host'] ?? ''));
        $refererHost = strtolower((string) ($parts['host'] ?? ''));
        if ($currentHost === '' || $refererHost === '' || $currentHost !== $refererHost) {
            return false;
        }

        $currentScheme = strtolower((string) ($current['scheme'] ?? ''));
        $refererScheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($currentScheme !== $refererScheme) {
            return false;
        }

        $currentPort = (int) ($current['port'] ?? ($currentScheme === 'https' ? 443 : 80));
        $refererPort = (int) ($parts['port'] ?? ($refererScheme === 'https' ? 443 : 80));

        return $currentPort === $refererPort;
    }

    private function parseXml($message): string
    {
        if (is_array($message)) {
            $result = '';

            foreach ($message as $key => $val) {
                $tagName = is_int($key) ? 'item' : $key;
                $result .= '<' . $tagName . '>' . $this->parseXml($val) . '</' . $tagName . '>';
            }

            return $result;
        } else {
            return preg_match("/^[^<>]+$/is", $message) ? $message : '<![CDATA[' . $message . ']]>';
        }
    }
}
