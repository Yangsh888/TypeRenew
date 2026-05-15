<?php

namespace Typecho;

use Typecho\Router\ParamsDelegateInterface;
use Typecho\Router\Parser;
use Typecho\Router\Exception as RouterException;

class Router
{
    public static string $current;

    private static array $routingTable = [];

    private static bool $matched = false;

    /**
     * 解析路径
     *
     * @param string $pathInfo 全路径
     * @param mixed $parameter 输入参数
     * @param bool $once 是否只匹配一次
     * @return false|Widget
     * @throws \Exception
     */
    public static function match(string $pathInfo, $parameter = null, bool $once = true)
    {
        if ($once && self::$matched) {
            throw new RouterException("Path '{$pathInfo}' not found", 404);
        }

        self::$matched = true;

        foreach (self::route($pathInfo) as $result) {
            [$route, $params] = $result;
            try {
                return Widget::widget($route['widget'], $parameter, $params);
            } catch (\Throwable $e) {
                if (404 == $e->getCode()) {
                    Widget::destroy($route['widget']);
                    continue;
                }

                throw $e;
            }
        }

        return false;
    }

    /**
     * 路由分发函数
     *
     * @throws RouterException|\Exception
     */
    public static function dispatch()
    {
        $pathInfo = Request::getInstance()->getPathInfo();

        foreach (self::route($pathInfo) as $result) {
            [$route, $params] = $result;

            try {
                $widget = Widget::widget($route['widget'], null, $params);

                if (isset($route['action'])) {
                    $widget->{$route['action']}();
                }

                return;
            } catch (\Throwable $e) {
                if (404 == $e->getCode()) {
                    Widget::destroy($route['widget']);
                    continue;
                }

                throw $e;
            }
        }

        throw new RouterException("Path '{$pathInfo}' not found", 404);
    }

    /**
     * 路由反解析函数
     *
     * @param string $name 路由配置表名称
     * @param mixed $value 路由填充值
     * @param string|null $prefix 最终合成路径的前缀
     * @return string
     */
    public static function url(
        string $name,
        $value = null,
        ?string $prefix = null
    ): string {
        if (!isset(self::$routingTable[$name])) {
            return '#';
        }

        $route = self::$routingTable[$name];

        $pattern = [];
        foreach ($route['params'] as $param) {
            if (is_array($value) && isset($value[$param])) {
                $pattern[$param] = $value[$param];
            } elseif ($value instanceof ParamsDelegateInterface) {
                $pattern[$param] = $value->getRouterParam($param);
            } else {
                $pattern[$param] = '{' . $param . '}';
            }
        }

        return Common::url(vsprintf($route['format'], $pattern), $prefix);
    }

    /**
     * 设置路由器默认配置
     *
     * @param mixed $routes 配置信息
     * @return void
     */
    public static function setRoutes($routes)
    {
        if (isset($routes[0])) {
            self::$routingTable = $routes[0];
        } else {
            $parser = new Parser($routes);
            self::$routingTable = $parser->parse();
        }
    }

    /**
     * 获取路由信息
     *
     * @param string $routeName 路由名称
     * @return mixed
     */
    public static function get(string $routeName)
    {
        return self::$routingTable[$routeName] ?? null;
    }

    /**
     * @param string $pathInfo
     * @return \Generator
     * @throws \Exception
     */
    private static function route(string $pathInfo): \Generator
    {
        foreach (self::$routingTable as $key => $route) {
            if (preg_match($route['regx'], $pathInfo, $matches)) {
                self::$current = $key;

                $params = null;

                if (!empty($route['params'])) {
                    unset($matches[0]);
                    $params = array_combine($route['params'], $matches);
                }

                yield [$route, $params];
            }
        }
    }
}
