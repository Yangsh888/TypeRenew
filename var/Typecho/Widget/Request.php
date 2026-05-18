<?php

namespace Typecho\Widget;

use Typecho\Config;
use Typecho\Request as HttpRequest;

class Request
{
    private const FILTERS = [
        'int'     => 'intval',
        'integer' => 'intval',
        'encode'  => 'urlencode',
        'html'    => 'htmlspecialchars',
        'search'  => ['\Typecho\Common', 'filterSearchQuery'],
        'xss'     => ['\Typecho\Common', 'removeXSS'],
        'url'     => ['\Typecho\Common', 'safeUrl'],
        'slug'    => ['\Typecho\Common', 'slugName']
    ];

    private array $filter = [];

    private HttpRequest $request;

    private Config $params;

    public function __construct(HttpRequest $request, ?Config $params = null)
    {
        $this->request = $request;
        $this->params = $params ?? new Config();
    }

    public function setParam(string $name, $value)
    {
        $this->params[$name] = $value;
    }

    public function setParams(array $params): void
    {
        $this->params->setDefault($params);
    }

    public function filter(...$filters): Request
    {
        foreach ($filters as $filter) {
            $this->filter[] = $this->wrapFilter(
                is_string($filter) && isset(self::FILTERS[$filter])
                ? self::FILTERS[$filter] : $filter
            );
        }

        return $this;
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

    public function get(string $key, $default = null, ?bool &$exists = true)
    {
        return $this->applyFilter($this->request->proxy($this->params)->get($key, $default, $exists));
    }

    public function getArray(string $key): array
    {
        return $this->applyFilter($this->request->proxy($this->params)->getArray($key));
    }

    public function from(...$params): array
    {
        return $this->applyFilter($this->request->proxy($this->params)->from(...$params));
    }

    public function is(string|array $query): bool
    {
        $result = $this->request->proxy($this->params)->is($query);
        $this->request->endProxy();
        return $result;
    }

    public function getRequestRoot(): string
    {
        return $this->request->getRequestRoot();
    }

    public function getRequestUrl(): string
    {
        return $this->request->getRequestUrl();
    }

    public function getRequestUri(): ?string
    {
        return $this->request->getRequestUri();
    }

    public function getPathInfo(): ?string
    {
        return $this->request->getPathInfo();
    }

    public function getUrlPrefix(): ?string
    {
        return $this->request->getUrlPrefix();
    }

    public function makeUriByRequest($parameter = null): string
    {
        return $this->request->makeUriByRequest($parameter);
    }

    public function getContentType(): ?string
    {
        return $this->request->getContentType();
    }

    public function getServer(string $name, ?string $default = null): ?string
    {
        return $this->request->getServer($name, $default);
    }

    public function getIp(): string
    {
        return $this->request->getIp();
    }

    public function getHeader(string $key, ?string $default = null): ?string
    {
        return $this->request->getHeader($key, $default);
    }

    public function getAgent(): ?string
    {
        return $this->request->getAgent();
    }

    public function getReferer(): ?string
    {
        return $this->request->getReferer();
    }

    public function isSecure(): bool
    {
        return $this->request->isSecure();
    }

    public function isGet(): bool
    {
        return $this->request->isGet();
    }

    public function isPost(): bool
    {
        return $this->request->isPost();
    }

    public function isPut(): bool
    {
        return $this->request->isPut();
    }

    public function isAjax(): bool
    {
        return $this->request->isAjax();
    }

    public function isJson(): bool
    {
        return $this->request->isJson();
    }

    public function getRawBody(): string
    {
        return $this->request->getRawBody();
    }

    public function getJsonBody(): array
    {
        return $this->request->getJsonBody();
    }

    private function applyFilter($value)
    {
        if ($this->filter) {
            foreach ($this->filter as $filter) {
                $value = $this->applyFilterRecursive($value, $filter);
            }

            $this->filter = [];
        }

        $this->request->endProxy();
        return $value;
    }

    // 递归处理数组叶子节点，避免深层值绕过过滤链路。
    private function applyFilterRecursive($value, callable $filter)
    {
        if (!is_array($value)) {
            return $filter($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->applyFilterRecursive($item, $filter);
        }

        return $value;
    }

    private function wrapFilter(callable $filter): callable
    {
        return function ($value) use ($filter) {
            return $filter($value ?? '');
        };
    }
}
