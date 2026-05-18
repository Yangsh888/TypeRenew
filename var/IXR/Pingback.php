<?php

namespace IXR;

use Typecho\Common;
use Typecho\Http\Client as HttpClient;
use Typecho\Http\Client\Exception as HttpException;

#[\AllowDynamicProperties]
class Pingback
{
    private string $html;

    private string $target;

    public function __construct(string $url, string $target)
    {
        $client = HttpClient::get();
        $this->target = $target;
        $sourceHost = $this->extractHost($url);

        if (!isset($client)) {
            throw new Exception('No available http client', 50);
        }

        if ($sourceHost === '' || !Common::checkSafeHost($sourceHost)) {
            throw new Exception('Pingback source host is not safe', 50);
        }

        try {
            $client->setTimeout(5);
            if (defined('CURLOPT_FOLLOWLOCATION')) {
                $client->setOption(CURLOPT_FOLLOWLOCATION, false);
            }
            if (defined('CURLOPT_MAXREDIRS')) {
                $client->setOption(CURLOPT_MAXREDIRS, 0);
            }
            $client->send($url);
        } catch (HttpException $e) {
            throw new Exception('Pingback http error', 50);
        }

        $status = $client->getResponseStatus();
        if ($status >= 300 && $status < 400) {
            throw new Exception('Pingback redirect is not allowed', 50);
        }

        if ($status != 200) {
            throw new Exception('Pingback wrong http status', 50);
        }

        $responseUrl = $client->getResponseUrl();
        if ($responseUrl !== '') {
            $responseHost = $this->extractHost($responseUrl);
            if ($responseHost === '' || !Common::checkSafeHost($responseHost)) {
                throw new Exception('Pingback source host is not safe', 50);
            }

            if (strcasecmp($sourceHost, $responseHost) !== 0) {
                throw new Exception('Pingback redirect is not allowed', 50);
            }
        }

        $response = $client->getResponseBody();
        $encoding = $this->detectEncoding($client->getResponseHeader('Content-Type'), $response);
        $this->html = $this->normalizeHtml($response, $encoding);

        if (
            !$client->getResponseHeader('X-Pingback') &&
            !preg_match_all("/<link[^>]*rel=[\"']pingback[\"'][^>]+href=[\"']([^\"']*)[\"'][^>]*>/i", $this->html)
        ) {
            throw new Exception("Source server doesn't support pingback", 50);
        }
    }

    private function extractHost(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        return (string) ($parts['host'] ?? '');
    }

    private function detectEncoding(string $contentType, string $response): string
    {
        if ($contentType !== '' && preg_match("/charset=([_a-z0-9-]+)/i", $contentType, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/<\?xml[^>]*encoding=["\']([_a-z0-9-]+)["\']/i', $response, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match("/<meta\s+charset=[\"']?([_a-z0-9-]+)/i", $response, $matches)) {
            return strtoupper($matches[1]);
        }

        if (
            preg_match(
                '/<meta[^>]+http-equiv=["\']content-type["\'][^>]+content=["\'][^"\']*charset=([_a-z0-9-]+)/i',
                $response,
                $matches
            )
        ) {
            return strtoupper($matches[1]);
        }

        return 'UTF-8';
    }

    private function normalizeHtml(string $response, string $encoding): string
    {
        if ($encoding === 'UTF-8' || !function_exists('mb_convert_encoding')) {
            return $response;
        }

        try {
            $converted = mb_convert_encoding($response, 'UTF-8', $encoding);
            return is_string($converted) ? $converted : $response;
        } catch (\ValueError $e) {
            return $response;
        }
    }

    public function getTitle(): string
    {
        if (preg_match("/<title>([^<]*?)<\/title>/is", $this->html, $matchTitle)) {
            return Common::subStr(Common::removeXSS(trim(strip_tags($matchTitle[1]))), 0, 150, '...');
        }

        return (string) (parse_url($this->target, PHP_URL_HOST) ?: '');
    }

    public function getContent(): string
    {
        /** 干掉html tag，只留下<a>*/
        $text = Common::stripTags($this->html, '<a href="">');

        /** 此处将$target quote,留着后面用*/
        $pregLink = preg_quote($this->target);

        $finalText = '';
        $matched = false;
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match("|<a[^>]*href=[\"']{$pregLink}[\"'][^>]*>(.*?)</a>|", $line)) {
                $candidate = Common::stripTags($line);
                if (strlen($candidate) > strlen($finalText)) {
                    $finalText = $candidate;
                    $matched = true;
                }
            }
        }

        if (!$matched) {
            throw new Exception("Source page doesn't have target url", 50);
        }

        return '[...]' . Common::subStr($finalText, 0, 200, '') . '[...]';
    }
}
