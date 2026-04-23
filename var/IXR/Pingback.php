<?php

namespace IXR;

use Typecho\Common;
use Typecho\Http\Client as HttpClient;
use Typecho\Http\Client\Exception as HttpException;

/**
 * fetch pingback
 */
#[\AllowDynamicProperties]
class Pingback
{
    /**
     * @var string
     */
    private string $html;

    /**
     * @var string
     */
    private string $target;

    /**
     * @param string $url
     * @param string $target
     * @throws Exception
     */
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
            $client->setTimeout(5)
                ->setOption(CURLOPT_FOLLOWLOCATION, false)
                ->setOption(CURLOPT_MAXREDIRS, 0)
                ->send($url);
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
        $encoding = 'UTF-8';
        $contentType = $client->getResponseHeader('Content-Type');

        if (!empty($contentType) && preg_match("/charset=([_a-z0-9-]+)/i", $contentType, $matches)) {
            $encoding = strtoupper($matches[1]);
        } elseif (preg_match("/<meta\s+charset=\"([_a-z0-9-]+)\"/i", $response, $matches)) {
            $encoding = strtoupper($matches[1]);
        }

        $this->html = $encoding == 'UTF-8' ? $response : mb_convert_encoding($response, 'UTF-8', $encoding);

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

    /**
     * get title
     *
     * @return string
     */
    public function getTitle(): string
    {
        if (preg_match("/<title>([^<]*?)<\/title>/is", $this->html, $matchTitle)) {
            return Common::subStr(Common::removeXSS(trim(strip_tags($matchTitle[1]))), 0, 150, '...');
        }

        return (string) (parse_url($this->target, PHP_URL_HOST) ?: '');
    }

    /**
     * get content
     *
     * @return string
     * @throws Exception
     */
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
