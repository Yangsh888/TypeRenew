<?php

namespace Widget;

use Typecho\Common;
use Typecho\Response;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Security extends Base
{
    private string $token;

    private bool $enabled = true;

    public function initComponents(int &$components)
    {
        $components = self::INIT_OPTIONS | self::INIT_USER;
    }

    public function execute()
    {
        $this->token = $this->options->secret;
        if ($this->user->hasLogin()) {
            $this->token .= '&' . $this->user->authCode . '&' . $this->user->uid;
        }
    }

    /**
     * @param bool $enabled
     */
    public function enable(bool $enabled = true)
    {
        $this->enabled = $enabled;
    }

    public function protect()
    {
        if (!$this->enabled) {
            return;
        }
        $current = (string) $this->request->get('_');
        $referer = (string) $this->request->getReferer();
        $requestUrl = (string) $this->request->getRequestUrl();
        $valid = hash_equals($this->getToken($referer), $current)
            || hash_equals($this->getToken($requestUrl), $current);

        if (!$valid && $this->allowLegacyToken()) {
            $valid = hash_equals($this->legacyToken($referer), $current)
                || hash_equals($this->legacyToken($requestUrl), $current);
        }

        if (!$valid) {
            $this->response->goBack();
        }
    }

    public function getToken(?string $suffix): string
    {
        return hash_hmac('sha256', (string) $suffix, $this->token);
    }

    public function getRootUrl(?string $path): string
    {
        return Common::url($this->getTokenUrl($path), $this->options->rootUrl);
    }

    /**
     * 生成带token的路径
     *
     * @param $path
     * @param string|null $url
     * @return string
     */
    public function getTokenUrl($path, ?string $url = null): string
    {
        $parts = Common::parseUrl((string) $path);
        if ($parts === [] && $path !== '') {
            $parts = ['path' => (string) $path];
        }
        $params = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
        }

        $params['_'] = $this->getToken($url ?: $this->request->getRequestUrl());
        $parts['query'] = http_build_query($params);

        return Common::buildUrl($parts);
    }

    public function adminUrl($path)
    {
        echo $this->getAdminUrl($path);
    }

    public function getAdminUrl(string $path): string
    {
        return Common::url($this->getTokenUrl($path), $this->options->adminUrl);
    }

    public function index($path)
    {
        echo $this->getIndex($path);
    }

    public function getIndex($path): string
    {
        return Common::url($this->getTokenUrl($path), $this->options->index);
    }

    private function legacyToken(?string $suffix): string
    {
        return md5($this->token . '&' . $suffix);
    }

    private function allowLegacyToken(): bool
    {
        return defined('__TYPECHO_ALLOW_LEGACY_TOKEN__') && __TYPECHO_ALLOW_LEGACY_TOKEN__;
    }
}
 
