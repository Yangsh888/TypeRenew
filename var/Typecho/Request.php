<?php

namespace Typecho;

class Request
{
    private static Request $instance;

    private ?Config $sandbox;

    private ?Config $params;

    private ?string $pathInfo = null;

    private ?string $requestUri = null;

    private ?string $requestRoot = null;

    private ?string $baseUrl = null;

    private ?string $ip = null;

    private ?string $rawBody = null;

    private ?array $jsonBody = null;

    private ?string $urlPrefix = null;

    private ?string $host = null;

    private static string $ipSource = '';

    public static function getInstance(): Request
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function configureIp(string $source): void
    {
        self::$ipSource = trim($source);
    }

    public function beginSandbox(Config $sandbox): Request
    {
        $this->sandbox = $sandbox;
        return $this;
    }

    public function endSandbox(): Request
    {
        $this->sandbox = null;
        return $this;
    }

    public function proxy(Config $params): Request
    {
        $this->params = $params;
        return $this;
    }

    public function endProxy(): Request
    {
        $this->params = null;
        return $this;
    }

    public function get(string $key, $default = null, ?bool &$exists = true)
    {
        $value = null;

        if (isset($this->params) && isset($this->params[$key])) {
            $value = $this->params[$key];
        } elseif (isset($this->sandbox)) {
            if (isset($this->sandbox[$key])) {
                $value = $this->sandbox[$key];
            }
        } elseif ($key === '@json') {
            if ($this->isJson()) {
                $value = $this->getJsonBody();
                $default = $default ?? $value;
            }
        } elseif (isset($_POST[$key])) {
            $value = $_POST[$key];
        } elseif (isset($_GET[$key])) {
            $value = $_GET[$key];
        }

        if (!isset($value)) {
            $exists = false;
            return $default;
        }

        $exists = true;
        return is_array($default) == is_array($value) ? $value : $default;
    }

    public function __get(string $key)
    {
        return $this->get($key);
    }

    public function __isset(string $key): bool
    {
        $this->get($key, null, $exists);
        return (bool) $exists;
    }

    public function getRawBody(): string
    {
        if ($this->rawBody !== null) {
            return $this->rawBody;
        }

        $body = file_get_contents('php://input');
        $this->rawBody = $body !== false ? $body : '';

        return $this->rawBody;
    }

    public function getJsonBody(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        if (!$this->isJson()) {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        try {
            $decoded = json_decode($this->getRawBody(), true, 16, JSON_THROW_ON_ERROR);
            $this->jsonBody = is_array($decoded) ? $decoded : [];
        } catch (\JsonException $e) {
            $this->jsonBody = [];
        }

        return $this->jsonBody;
    }

    public function getArray(string $key): array
    {
        $result = $this->get($key, [], $exists);

        if (!$exists) {
            return [];
        }

        if (is_array($result)) {
            return $result;
        }

        if ($result === null || $result === '') {
            return [];
        }

        return [$result];
    }

    public function from(string|array $params): array
    {
        $result = [];
        $args = is_array($params) ? $params : func_get_args();

        foreach ($args as $arg) {
            $result[$arg] = $this->get($arg);
        }

        return $result;
    }

    public function getRequestRoot(): string
    {
        if (null === $this->requestRoot) {
            $root = rtrim($this->getUrlPrefix() . $this->getBaseUrl(), '/') . '/';

            $pos = strrpos($root, '.php/');
            if ($pos !== false) {
                $root = dirname(substr($root, 0, $pos));
            }

            $this->requestRoot = rtrim($root, '/');
        }

        return $this->requestRoot;
    }

    public function getRequestUrl(): string
    {
        return $this->getUrlPrefix() . $this->getRequestUri();
    }

    public function makeUriByRequest($parameter = null): string
    {
        $requestUri = $this->getRequestUrl();
        $parts = Common::parseUrl($requestUri);

        if (is_string($parameter)) {
            parse_str($parameter, $args);
        } elseif (is_array($parameter)) {
            $args = $parameter;
        } else {
            return $requestUri;
        }

        if (isset($parts['query'])) {
            parse_str($parts['query'], $currentArgs);
            $args = array_merge($currentArgs, $args);
        }
        $parts['query'] = http_build_query($args);

        return Common::buildUrl($parts);
    }

    public function getPathInfo(): ?string
    {
        if (null !== $this->pathInfo) {
            return $this->pathInfo;
        }

        $pathInfo = null;

        $requestUri = $this->getRequestUri();
        $finalBaseUrl = $this->getBaseUrl();

        $pos = strpos($requestUri, '?');
        if ($pos !== false) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        if (
            (null !== $finalBaseUrl)
            && (false === ($pathInfo = substr($requestUri, strlen($finalBaseUrl))))
        ) {
            $pathInfo = '/';
        } elseif (null === $finalBaseUrl) {
            $pathInfo = $requestUri;
        }

        if (!empty($pathInfo)) {
            // 针对 IIS 的 pathinfo 编码，仅在 mbstring 可用时转换
            $pathInfo = defined('__TYPECHO_PATHINFO_ENCODING__') && function_exists('mb_convert_encoding') ?
                mb_convert_encoding($pathInfo, 'UTF-8', __TYPECHO_PATHINFO_ENCODING__) : $pathInfo;
        } else {
            $pathInfo = '/';
        }

        return ($this->pathInfo = '/' . ltrim(urldecode($pathInfo), '/'));
    }

    public function getContentType(): ?string
    {
        return $this->getHeader('Content-Type');
    }

    public function getServer(string $name, ?string $default = null): ?string
    {
        return $_SERVER[$name] ?? $default;
    }

    public function getIp(): string
    {
        if (null === $this->ip) {
            $this->ip = $this->resolveIp() ?: 'unknown';
        }

        return $this->ip;
    }

    private function resolveIp(): string
    {
        $remote = $this->filterIp($this->getServer('REMOTE_ADDR', ''));

        // 高安全场景：定义了可信代理白名单，仅当连接来自可信代理时才信任转发头。
        if (defined('__TYPECHO_TRUST_PROXY__')) {
            if (!$this->isTrustedProxy($remote)) {
                return $remote;
            }

            $source = $this->resolveIpSource();
            if ($source === '' || $source === 'REMOTE_ADDR') {
                $source = 'HTTP_X_FORWARDED_FOR';
            }

            return $this->ipFromSource($source) ?: $remote;
        }

        // 常规场景：按管理员选择的来源头取值（默认 REMOTE_ADDR，不信任任何转发头）。
        $source = $this->resolveIpSource();
        if ($source === '' || $source === 'REMOTE_ADDR') {
            return $remote;
        }

        return $this->ipFromSource($source) ?: $remote;
    }

    private function resolveIpSource(): string
    {
        if (defined('__TYPECHO_IP_SOURCE__')) {
            return $this->normalizeIpSource((string) __TYPECHO_IP_SOURCE__);
        }

        return $this->normalizeIpSource(self::$ipSource);
    }

    private function normalizeIpSource(string $source): string
    {
        $source = strtoupper(trim($source));
        if ($source === '') {
            return '';
        }

        $source = preg_replace('/[^A-Z0-9_]/', '_', $source) ?? '';
        if ($source === '' || $source === 'REMOTE_ADDR') {
            return $source;
        }

        // 允许填写 X_FORWARDED_FOR / X-Forwarded-For 等不带 HTTP_ 前缀的写法。
        if (!str_starts_with($source, 'HTTP_')) {
            $source = 'HTTP_' . $source;
        }

        return $source;
    }

    private function ipFromSource(string $source): string
    {
        $value = (string) $this->getServer($source, '');
        if ($value === '') {
            return '';
        }

        foreach (explode(',', $value) as $candidate) {
            $ip = $this->filterIp(trim($candidate));
            if ($ip !== '') {
                return $ip;
            }
        }

        return '';
    }

    private function filterIp(?string $ip): string
    {
        $value = trim((string) $ip);
        if ($value === '') {
            return '';
        }
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) ?: '';
    }

    private function isTrustedProxy(string $remote): bool
    {
        if ($remote === '') {
            return false;
        }

        if (defined('__TYPECHO_TRUST_PROXY__')) {
            $raw = trim((string) __TYPECHO_TRUST_PROXY__);
            if ($raw === '' || $raw === '0' || strtolower($raw) === 'false') {
                return false;
            }
            if ($raw === '*') {
                return true;
            }
            $rules = array_filter(array_map('trim', explode(',', $raw)));
            foreach ($rules as $rule) {
                if ($rule === $remote || $this->ipInCidr($remote, $rule)) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }
        [$subnet, $mask] = explode('/', $cidr, 2);
        if (!filter_var($subnet, FILTER_VALIDATE_IP) || !ctype_digit((string) $mask)) {
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $mask = (int) $mask;
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        if ($mask > $maxBits) {
            return false;
        }

        $bytes = intdiv($mask, 8);
        $bits = $mask % 8;
        if ($bytes > strlen($ipBin)) {
            return false;
        }
        if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }

        $maskByte = (~(0xff >> $bits)) & 0xff;
        return ((ord($ipBin[$bytes]) & $maskByte) === (ord($subnetBin[$bytes]) & $maskByte));
    }

    private function getTrustedHost(): string
    {
        if ($this->host !== null) {
            return $this->host;
        }

        $remote = $this->filterIp($this->getServer('REMOTE_ADDR', ''));
        $trusted = $remote !== '' && $this->isTrustedProxy($remote);
        $candidates = [];
        $fallbackPort = $this->resolvePort(
            $trusted ? $this->getHeader('X-Forwarded-Port') : null,
            $this->getServer('SERVER_PORT')
        );
        $defaultPort = $this->isSecure() ? 443 : 80;

        if ($trusted) {
            $forwardedHost = (string) $this->getHeader('X-Forwarded-Host', '');
            if ($forwardedHost !== '') {
                [$forwardedHost] = array_map('trim', explode(',', $forwardedHost));
                $candidates[] = $forwardedHost;
            }

            $forwarded = (string) $this->getHeader('Forwarded', '');
            if ($forwarded !== '' && preg_match('/(?:^|[,;])\s*host=(?:"?)(\[[^\]]+\]|[^";,\s]+)(?:"?)/i', $forwarded, $matches)) {
                $candidates[] = $matches[1];
            }
        }

        $candidates[] = (string) $this->getServer('HTTP_HOST', '');
        $candidates[] = (string) $this->getServer('SERVER_NAME', '');

        foreach ($candidates as $candidate) {
            $host = $this->sanitizeHost($candidate);
            if (
                $host !== ''
                && $fallbackPort !== null
                && $fallbackPort !== $defaultPort
                && !preg_match('/(?:\]:|:)\d{1,5}$/', $host)
            ) {
                $host .= ':' . $fallbackPort;
            }

            if ($host !== '') {
                return $this->host = $host;
            }
        }

        return $this->host = 'localhost';
    }

    private function resolvePort(?string ...$candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value === '') {
                continue;
            }

            if (str_contains($value, ',')) {
                [$value] = array_map('trim', explode(',', $value, 2));
            }

            if (!ctype_digit($value)) {
                continue;
            }

            $port = (int) $value;
            if ($port > 0 && $port <= 65535) {
                return $port;
            }
        }

        return null;
    }

    private function sanitizeHost(?string $host): string
    {
        $value = trim((string) $host);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, ',')) {
            [$value] = array_map('trim', explode(',', $value, 2));
        }

        if (str_contains($value, '://')) {
            $parsed = parse_url($value, PHP_URL_HOST);
            $port = parse_url($value, PHP_URL_PORT);
            $value = is_string($parsed) ? $parsed . ($port ? ':' . $port : '') : '';
        }

        if ($value === '' || preg_match('/[\/\\\\@\?#]/', $value)) {
            return '';
        }

        if (preg_match('/^\[(?<ip>[0-9a-f:.]+)\](?::(?<port>\d{1,5}))?$/i', $value, $matches)) {
            if (!filter_var($matches['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return '';
            }

            $port = (int)($matches['port'] ?? 0);
            if ($port < 0 || $port > 65535) {
                return '';
            }

            return '[' . strtolower($matches['ip']) . ']' . ($port > 0 ? ':' . $port : '');
        }

        if (!preg_match('/^(?<name>[A-Za-z0-9.-]+)(?::(?<port>\d{1,5}))?$/', $value, $matches)) {
            return '';
        }

        $name = strtolower($matches['name']);
        if ($name !== 'localhost' && !filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return '';
        }

        $port = (int)($matches['port'] ?? 0);
        if ($port < 0 || $port > 65535) {
            return '';
        }

        return $name . ($port > 0 ? ':' . $port : '');
    }

    public function getHeader(string $key, ?string $default = null): ?string
    {
        $key = strtoupper(str_replace('-', '_', $key));

        // Content-Type 和 Content-Length 这两个 header 还需要从不带 HTTP_ 的 key 尝试获取
        if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
            $default = $this->getServer($key, $default);
        }

        return $this->getServer('HTTP_' . $key, $default);
    }

    public function getAgent(): ?string
    {
        return $this->getHeader('User-Agent');
    }

    public function getReferer(): ?string
    {
        return $this->getHeader('Referer');
    }

    public function isSecure(): bool
    {
        $remote = $this->filterIp($this->getServer('REMOTE_ADDR', ''));
        $trusted = $remote !== '' && $this->isTrustedProxy($remote);

        $proto = $trusted ? (string) $this->getHeader('X-Forwarded-Proto', '') : '';
        $port = $trusted ? (string) $this->getHeader('X-Forwarded-Port', '') : '';
        if ($proto !== '') {
            $proto = strtolower($proto);
        }

        return ($proto !== '' && ($proto === 'https' || $proto === 'quic'))
            || ($port !== '' && (int) $port === 443)
            || (!empty($_SERVER['HTTPS']) && 'off' != strtolower((string) $_SERVER['HTTPS']))
            || (!empty($_SERVER['SERVER_PORT']) && 443 == (int) $_SERVER['SERVER_PORT'])
            || (defined('__TYPECHO_SECURE__') && __TYPECHO_SECURE__);
    }

    public function isCli(): bool
    {
        return php_sapi_name() === 'cli';
    }

    public function isGet(): bool
    {
        return 'GET' === $this->getServer('REQUEST_METHOD');
    }

    public function isPost(): bool
    {
        return 'POST' === $this->getServer('REQUEST_METHOD');
    }

    public function isPut(): bool
    {
        return 'PUT' === $this->getServer('REQUEST_METHOD');
    }

    public function isAjax(): bool
    {
        return 'XMLHttpRequest' === $this->getHeader('X-Requested-With');
    }

    public function isJson(): bool
    {
        return !!preg_match(
            "/^\s*application\/json(;|$)/i",
            $this->getContentType() ?? ''
        );
    }

    public function is(string|array $query): bool
    {
        $validated = false;
        $params = [];

        if (is_string($query)) {
            parse_str($query, $params);
        } elseif (is_array($query)) {
            $params = $query;
        }

        if (!empty($params)) {
            $validated = true;
            foreach ($params as $key => $val) {
                $param = $this->get($key, null, $exists);
                $validated = empty($val) ? $exists : ($val == $param);

                if (!$validated) {
                    break;
                }
            }
        }

        return $validated;
    }

    public function getRequestUri(): ?string
    {
        if (!empty($this->requestUri)) {
            return $this->requestUri;
        }

        $requestUri = '/';

        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
            $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (
            isset($_SERVER['IIS_WasUrlRewritten'])
            && $_SERVER['IIS_WasUrlRewritten'] == '1'
            && isset($_SERVER['UNENCODED_URL'])
            && $_SERVER['UNENCODED_URL'] != ''
        ) {
            $requestUri = $_SERVER['UNENCODED_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            $parts = parse_url($requestUri);
            $host = $this->getTrustedHost();

            if ($host !== '' && str_contains($requestUri, $host)) {
                if (false !== $parts) {
                    $requestUri = (empty($parts['path']) ? '' : $parts['path'])
                        . ((empty($parts['query'])) ? '' : '?' . $parts['query']);
                }
            } elseif (!empty($_SERVER['QUERY_STRING']) && empty($parts['query'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        return $this->requestUri = $requestUri;
    }

    public function getUrlPrefix(): ?string
    {
        if (empty($this->urlPrefix)) {
            if (defined('__TYPECHO_URL_PREFIX__')) {
                $this->urlPrefix = __TYPECHO_URL_PREFIX__;
            } elseif (php_sapi_name() != 'cli') {
                $this->urlPrefix = ($this->isSecure() ? 'https' : 'http') . '://' . $this->getTrustedHost();
            }
        }

        return $this->urlPrefix;
    }

    private function getBaseUrl(): ?string
    {
        if (null !== $this->baseUrl) {
            return $this->baseUrl;
        }

        $filename = (isset($_SERVER['SCRIPT_FILENAME'])) ? basename($_SERVER['SCRIPT_FILENAME']) : '';

        if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $filename) {
            $baseUrl = $_SERVER['SCRIPT_NAME'];
        } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $filename) {
            $baseUrl = $_SERVER['PHP_SELF'];
        } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $filename) {
            $baseUrl = $_SERVER['ORIG_SCRIPT_NAME'];
        } else {
            $path = $_SERVER['PHP_SELF'] ?? '';
            $file = $_SERVER['SCRIPT_FILENAME'] ?? '';
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/' . $seg . $baseUrl;
                ++$index;
            } while (($last > $index) && (false !== ($pos = strpos($path, $baseUrl))) && (0 != $pos));
        }

        $finalBaseUrl = null;
        $requestUri = $this->getRequestUri();

        if (0 === strpos($requestUri, $baseUrl)) {
            $finalBaseUrl = $baseUrl;
        } elseif (0 === strpos($requestUri, dirname($baseUrl))) {
            $finalBaseUrl = rtrim(dirname($baseUrl), '/');
        } elseif (false === strpos($requestUri, basename($baseUrl))) {
            $finalBaseUrl = '';
        } elseif (
            (strlen($requestUri) >= strlen($baseUrl))
            && ((false !== ($pos = strpos($requestUri, $baseUrl))) && ($pos !== 0))
        ) {
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        return ($this->baseUrl = (null === $finalBaseUrl) ? rtrim($baseUrl, '/') : $finalBaseUrl);
    }
}
