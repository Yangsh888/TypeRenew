<?php

namespace Typecho\Widget;

use Typecho\Config;
use Typecho\Request as HttpRequest;

/**
 * Widget Request Wrapper
 */
class Request
{
    /**
     * 支持的过滤器列表
     * @var string
     */
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

    /**
     * 当前过滤器
     * @var array
     */
    private array $filter = [];

    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * @var Config
     */
    private Config $params;

    /**
     * @param HttpRequest $request
     * @param Config|null $params
     */
    public function __construct(HttpRequest $request, ?Config $params = null)
    {
        $this->request = $request;
        $this->params = $params ?? new Config();
    }

    /**
     * 设置http传递参数
     * @param string $name 指定的参数
     * @param mixed $value 参数值
     * @return void
     */
    public function setParam(string $name, $value)
    {
        $this->params[$name] = $value;
    }

    /**
     * 设置多个参数
     * @param array $params 参数列表
     * @return void
     */
    public function setParams(array $params): void
    {
        $this->params->setDefault($params);
    }

    /**
     * Add filter to request
     *
     * @param string|callable ...$filters
     * @return $this
     */
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
        return $this->applyFilter(call_user_func_array([$this->request->proxy($this->params), 'from'], $params));
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

    /**
     * 应用过滤器
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function applyFilter($value)
    {
        if ($this->filter) {
            foreach ($this->filter as $filter) {
                $value = is_array($value) ? array_map($filter, $value) :
                    call_user_func($filter, $value);
            }

            $this->filter = [];
        }

        $this->request->endProxy();
        return $value;
    }

    /**
     * Wrap a filter to make sure it always receives a string.
     *
     * @param callable $filter
     *
     * @return callable
     */
    private function wrapFilter(callable $filter): callable
    {
        return function ($value) use ($filter) {
            return call_user_func($filter, $value ?? '');
        };
    }
}
