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

    /**
     * 域名前缀
     *
     * @var string|null
     */
    private ?string $urlPrefix = null;

    private ?string $host = null;

    public static function getInstance(): Request
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 初始化变量
     *
     * @return $this
     */
    public function beginSandbox(Config $sandbox): Request
    {
        $this->sandbox = $sandbox;
        return $this;
    }

    /**
     * @return $this
     */
    public function endSandbox(): Request
    {
        $this->sandbox = null;
        return $this;
    }

    /**
     * @param Config $params
     * @return $this
     */
    public function proxy(Config $params): Request
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return $this
     */
    public function endProxy(): Request
    {
        if (isset($this->params)) {
            $this->params = null;
        }

        return $this;
    }

    /**
     * 获取实际传递参数
     *
     * @param string $key 指定参数
     * @param mixed $default 默认参数 (default: NULL)
     * @param bool|null $exists detect exists
     * @return mixed
     */
    public function get(string $key, $default = null, ?bool &$exists = true)
    {
        $value = null;

        switch (true) {
            case isset($this->params) && isset($this->params[$key]):
                $value = $this->params[$key];
                break;
            case isset($this->sandbox):
                if (isset($this->sandbox[$key])) {
                    $value = $this->sandbox[$key];
                }
                break;
            case $key === '@json':
                if ($this->isJson()) {
                    $value = $this->getJsonBody();
                    $default = $default ?? $value;
                }
                break;
            case isset($_GET[$key]):
                $value = $_GET[$key];
                break;
            case isset($_POST[$key]):
                $value = $_POST[$key];
                break;
            default:
                break;
        }

        if (isset($value) && $value !== '') {
            $exists = true;
            if (is_array($default) == is_array($value)) {
                return $value;
            } else {
                return $default;
            }
        } else {
            $exists = false;
            return $default;
        }
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

        $decoded = json_decode($this->getRawBody(), true, 16);
        $this->jsonBody = is_array($decoded) ? $decoded : [];

        return $this->jsonBody;
    }

    /**
     * 获取一个数组
     *
     * @param string $key
     * @return array
     */
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

    /**
     * 从参数列表指定的值中获取http传递参数
     *
     * @param string|array $params 指定的参数
     * @return array
     */
    public function from(string|array $params): array
    {
        $result = [];
        $args = is_array($params) ? $params : func_get_args();

        foreach ($args as $arg) {
            $result[$arg] = $this->get($arg);
        }

        return $result;
    }

    /**
     * getRequestRoot
     *
     * @return string
     */
    public function getRequestRoot(): string
    {
        if (null === $this->requestRoot) {
            $root = rtrim($this->getUrlPrefix() . $this->getBaseUrl(), '/') . '/';

            $pos = strrpos($root, '.php/');
            if ($pos) {
                $root = dirname(substr($root, 0, $pos));
            }

            $this->requestRoot = rtrim($root, '/');
        }

        return $this->requestRoot;
    }

    /**
     * 获取当前请求url
     *
     * @return string
     */
    public function getRequestUrl(): string
    {
        return $this->getUrlPrefix() . $this->getRequestUri();
    }

    /**
     * 根据当前uri构造指定参数的uri
     *
     * @param mixed $parameter 指定的参数
     * @return string
     */
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

    /**
     * 获取当前pathinfo
     *
     * @return string
     */
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
            // If substr() returns false then PATH_INFO is set to an empty string
            $pathInfo = '/';
        } elseif (null === $finalBaseUrl) {
            $pathInfo = $requestUri;
        }

        if (!empty($pathInfo)) {
            //针对iis的utf8编码做强制转换
            $pathInfo = defined('__TYPECHO_PATHINFO_ENCODING__') ?
                mb_convert_encoding($pathInfo, 'UTF-8', __TYPECHO_PATHINFO_ENCODING__) : $pathInfo;
        } else {
            $pathInfo = '/';
        }

        return ($this->pathInfo = '/' . ltrim(urldecode($pathInfo), '/'));
    }

    /**
     * 获取请求的内容类型
     *
     * @return string|null
     */
    public function getContentType(): ?string
    {
        return $this->getHeader('Content-Type');
    }

    /**
     * 获取环境变量
     *
     * @param string $name 获取环境变量名
     * @param string|null $default
     * @return string|null
     */
    public function getServer(string $name, ?string $default = null): ?string
    {
        return $_SERVER[$name] ?? $default;
    }

    /**
     * 获取ip地址
     *
     * @return string
     */
    public function getIp(): string
    {
        if (null === $this->ip) {
            $remote = $this->filterIp($this->getServer('REMOTE_ADDR', ''));
            $header = defined('__TYPECHO_IP_SOURCE__') ? __TYPECHO_IP_SOURCE__ : 'X-Forwarded-For';
            $ip = $remote;
            if ($this->isTrustedProxy($remote)) {
                $candidate = $this->getHeader($header, $this->getHeader('Client-Ip', $remote));
                if (!empty($candidate)) {
                    [$candidate] = array_map('trim', explode(',', $candidate));
                    $candidate = $this->filterIp($candidate);
                    if ($candidate !== '') {
                        $ip = $candidate;
                    }
                }
            }

            if (!empty($ip)) {
                $this->ip = $ip;
            } else {
                $this->ip = 'unknown';
            }
        }

        return $this->ip;
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
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
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
            if ($host !== '') {
                return $this->host = $host;
            }
        }

        return $this->host = 'localhost';
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

            $port = isset($matches['port']) ? (int) $matches['port'] : 0;
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

        $port = isset($matches['port']) ? (int) $matches['port'] : 0;
        if ($port < 0 || $port > 65535) {
            return '';
        }

        return $name . ($port > 0 ? ':' . $port : '');
    }

    /**
     * get header value
     *
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public function getHeader(string $key, ?string $default = null): ?string
    {
        $key = strtoupper(str_replace('-', '_', $key));

        // Content-Type 和 Content-Length 这两个 header 还需要从不带 HTTP_ 的 key 尝试获取
        if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
            $default = $this->getServer($key, $default);
        }

        return $this->getServer('HTTP_' . $key, $default);
    }

    /**
     * 获取客户端
     *
     * @return string
     */
    public function getAgent(): ?string
    {
        return $this->getHeader('User-Agent');
    }

    /**
     * 获取来源页
     *
     * @return string|null
     */
    public function getReferer(): ?string
    {
        return $this->getHeader('Referer');
    }

    /**
     * 判断是否为https
     *
     * @return bool
     */
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

    /**
     * @return bool
     */
    public function isCli(): bool
    {
        return php_sapi_name() == 'cli';
    }

    public function isGet(): bool
    {
        return 'GET' == $this->getServer('REQUEST_METHOD');
    }

    public function isPost(): bool
    {
        return 'POST' == $this->getServer('REQUEST_METHOD');
    }

    public function isPut(): bool
    {
        return 'PUT' == $this->getServer('REQUEST_METHOD');
    }

    public function isAjax(): bool
    {
        return 'XMLHttpRequest' == $this->getHeader('X-Requested-With');
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

    /**
     * 获取请求资源地址
     *
     * @return string|null
     */
    public function getRequestUri(): ?string
    {
        if (!empty($this->requestUri)) {
            return $this->requestUri;
        }

        $requestUri = '/';

        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // check this first so IIS will catch
            $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (
            // IIS7 with URL Rewrite: make sure we get the unencoded url (double slash problem)
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
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        return $this->requestUri = $requestUri;
    }

    /**
     * 获取url前缀
     *
     * @return string|null
     */
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

    /**
     * getBaseUrl
     *
     * @return string
     */
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
            $baseUrl = $_SERVER['ORIG_SCRIPT_NAME']; // 1and1 shared hosting compatibility
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
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

        // Does the baseUrl have anything in common with the request_uri?
        $finalBaseUrl = null;
        $requestUri = $this->getRequestUri();

        if (0 === strpos($requestUri, $baseUrl)) {
            // full $baseUrl matches
            $finalBaseUrl = $baseUrl;
        } elseif (0 === strpos($requestUri, dirname($baseUrl))) {
            // directory portion of $baseUrl matches
            $finalBaseUrl = rtrim(dirname($baseUrl), '/');
        } elseif (false === strpos($requestUri, basename($baseUrl))) {
            // no match whatsoever; set it blank
            $finalBaseUrl = '';
        } elseif (
            (strlen($requestUri) >= strlen($baseUrl))
            && ((false !== ($pos = strpos($requestUri, $baseUrl))) && ($pos !== 0))
        ) {
            // If using mod_rewrite or ISAPI_Rewrite strip the script filename
            // out of baseUrl. $pos !== 0 makes sure it is not matching a value
            // from PATH_INFO or QUERY_STRING
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        return ($this->baseUrl = (null === $finalBaseUrl) ? rtrim($baseUrl, '/') : $finalBaseUrl);
    }
}
