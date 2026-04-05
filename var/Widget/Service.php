<?php

namespace Widget;

use Typecho\Common;
use Typecho\Http\Client;
use Typecho\Response;
use Typecho\Widget\Exception;
use Widget\Base\Contents;
use Widget\Base\Options as BaseOptions;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Service extends BaseOptions implements ActionInterface
{
    public array $asyncRequests = [];

    public function sendPingHandle()
    {
        $data = $this->request->get('@json');
        $token = $data['token'] ?? '';
        $permalink = $data['permalink'] ?? '';
        $title = $data['title'] ?? '';
        $excerpt = $data['excerpt'] ?? '';

        $response = ['trackback' => [], 'pingback' => []];

        if (!Common::timeTokenValidate($token, $this->options->secret, 3) || empty($permalink)) {
            throw new Exception(_t('禁止访问'), 403);
        }

        $this->response->throwFinish();

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(30);
        }

        if (!empty($data['pingback'])) {
            $links = $data['pingback'];
            $permalinkPart = parse_url($permalink);
            foreach ($links as $url) {
                $urlPart = parse_url($url);

                if (isset($urlPart['scheme'])) {
                    if ('http' != $urlPart['scheme'] && 'https' != $urlPart['scheme']) {
                        continue;
                    }
                } else {
                    $urlPart['scheme'] = 'http';
                    $url = Common::buildUrl($urlPart);
                }

                if ($permalinkPart['host'] == $urlPart['host']) {
                    continue;
                }

                $spider = Client::get();

                if ($spider) {
                    try {
                        $spider->setTimeout(10)
                            ->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1)
                            ->send($url);

                        if (!($xmlrpcUrl = $spider->getResponseHeader('x-pingback'))) {
                            if (
                                preg_match(
                                    "/<link[^>]*rel=[\"']pingback[\"'][^>]*href=[\"']([^\"']+)[\"'][^>]*>/i",
                                    $spider->getResponseBody(),
                                    $out
                                )
                            ) {
                                $xmlrpcUrl = $out[1];
                            }
                        }

                        if (!empty($xmlrpcUrl)) {
                            $response['pingback'][] = $url;

                            try {
                                $xmlrpc = new \IXR\Client($xmlrpcUrl);
                                $xmlrpc->pingback->ping($permalink, $url);
                                unset($xmlrpc);
                            } catch (\IXR\Exception $e) {
                                $this->reportException('sendPingHandle.pingback', $e);
                            }
                        }
                    } catch (Client\Exception $e) {
                        $this->reportException('sendPingHandle.spider', $e);
                    }

                    unset($spider);
                }
            }
        }

        if (!empty($data['trackback'])) {
            $links = $data['trackback'];

            foreach ($links as $url) {
                $client = Client::get();
                $response['trackback'][] = $url;

                if ($client) {
                    try {
                        $client->setTimeout(5)
                            ->setOption(CURLOPT_CONNECTTIMEOUT, 3)
                            ->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1)
                            ->setData([
                                'blog_name' => $this->options->title . ' &raquo ' . $title,
                                'url' => $permalink,
                                'excerpt' => $excerpt
                            ])
                            ->send($url);

                        unset($client);
                    } catch (Client\Exception $e) {
                        $this->reportException('sendPingHandle.trackback', $e);
                    }
                }
            }
        }

        $this->response->throwJson($response);
    }

    public function sendPing(Contents $content, ?array $trackback = null)
    {
        $this->user->pass('contributor');

        if ($client = Client::get()) {
            try {
                $input = [
                    'do' => 'ping',
                    'permalink' => $content->permalink,
                    'excerpt' => $content->excerpt,
                    'title' => $content->title,
                    'token' => Common::timeToken($this->options->secret)
                ];

                if (preg_match_all("|<a[^>]*href=[\"'](.*?)[\"'][^>]*>(.*?)</a>|", $content->content, $matches)) {
                    $pingback = array_unique($matches[1]);

                    if (!empty($pingback)) {
                        $input['pingback'] = $pingback;
                    }
                }

                if (!empty($trackback)) {
                    $input['trackback'] = $trackback;
                }

                $client->setHeader('User-Agent', $this->options->generator)
                    ->setTimeout(5)
                    ->setOption(CURLOPT_CONNECTTIMEOUT, 3)
                    ->setJson($input)
                    ->send($this->getServiceUrl('ping'));
            } catch (Client\Exception $e) {
                $this->reportException('sendPing', $e);
            }
        }
    }

    private function getServiceUrl(string $do): string
    {
        $url = Common::url('/action/service', $this->options->index);

        if (defined('__TYPECHO_SERVICE_URL__')) {
            $rootPath = rtrim(parse_url($this->options->rootUrl, PHP_URL_PATH), '/');
            $path = parse_url($url, PHP_URL_PATH);
            $parts = parse_url(__TYPECHO_SERVICE_URL__);

            if (
                !empty($parts['path'])
                && $parts['path'] != '/'
                && rtrim($parts['path'], '/') != $rootPath
            ) {
                $path = Common::url($path, $parts['path']);
            }

            $parts['path'] = $path;
            $url = Common::buildUrl($parts);
        }

        return $url . '?do=' . $do;
    }

    public function requestService($method, ...$params)
    {
        static $called;

        if (!$called) {
            Response::getInstance()->addResponder(function () {
                if (!empty($this->asyncRequests) && $client = Client::get()) {
                    try {
                        $client->setHeader('User-Agent', $this->options->generator)
                            ->setTimeout(2)
                            ->setJson([
                                'requests' => $this->asyncRequests,
                                'token' => Common::timeToken($this->options->secret)
                            ])
                            ->send($this->getServiceUrl('async'));
                    } catch (Client\Exception $e) {
                        $this->reportException('requestService', $e);
                        return;
                    }
                }
            });

            $called = true;
        }

        $this->asyncRequests[] = [$method, $params];
    }

    public function asyncHandle()
    {
        $data = $this->request->get('@json');
        $token = $data['token'] ?? '';

        if (!Common::timeTokenValidate($token, $this->options->secret, 3)) {
            throw new Exception(_t('禁止访问'), 403);
        }

        $this->response->throwFinish();

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(30);
        }

        $requests = $data['requests'] ?? null;
        $plugin = self::pluginHandle();

        if (!empty($requests)) {
            foreach ($requests as $request) {
                [$method, $params] = $request;
                $plugin->call($method, ... $params);
            }
        }
    }

    public function action()
    {
        $this->on($this->request->isPost() && $this->request->is('do=ping'))->sendPingHandle();
        $this->on($this->request->isPost() && $this->request->is('do=async'))->asyncHandle();
    }

    private function reportException(string $scope, \Throwable $e): void
    {
        error_log('Service.' . $scope . ': ' . $e->getMessage());
    }
}
