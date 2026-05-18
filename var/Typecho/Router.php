<?php

namespace Typecho;

use Typecho\Router\ParamsDelegateInterface;
use Typecho\Router\Parser;
use Typecho\Router\Exception as RouterException;

class Router
{
    public static string $current = '';

    private static array $routingTable = [];

    private static bool $matched = false;

    public static function match(string $pathInfo, $parameter = null, bool $once = true)
    {
        $previousMatched = self::$matched;
        $previousCurrent = self::$current;

        if ($once && self::$matched) {
            throw new RouterException("Path '{$pathInfo}' not found", 404);
        }

        self::$matched = true;

        try {
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
        } finally {
            self::$matched = $previousMatched;
            self::$current = $previousCurrent;
        }
    }

    public static function dispatch()
    {
        $pathInfo = Request::getInstance()->getPathInfo();

        foreach (self::route($pathInfo) as $result) {
            [$route, $params] = $result;

            try {
                $widget = Widget::widget($route['widget'], null, $params);

                if (isset($route['action'])) {
                    $action = (string) $route['action'];

                    if (!self::isPublicAction($widget, $action)) {
                        throw new RouterException("Route action '{$action}' is not callable", 500);
                    }

                    $widget->{$action}();
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

    public static function setRoutes($routes)
    {
        if (isset($routes[0])) {
            self::$routingTable = $routes[0];
        } else {
            $parser = new Parser($routes);
            self::$routingTable = $parser->parse();
        }
    }

    public static function get(string $routeName)
    {
        return self::$routingTable[$routeName] ?? null;
    }

    private static function route(string $pathInfo): \Generator
    {
        foreach (self::$routingTable as $key => $route) {
            if (preg_match($route['regx'], $pathInfo, $matches)) {
                self::$current = $key;

                $params = null;

                if (!empty($route['params'])) {
                    unset($matches[0]);
                    if (count($route['params']) !== count($matches)) {
                        continue;
                    }

                    $params = array_combine($route['params'], $matches);
                }

                yield [$route, $params];
            }
        }
    }

    private static function isPublicAction(object $widget, string $action): bool
    {
        if ($action === '' || !method_exists($widget, $action)) {
            return false;
        }

        try {
            $method = new \ReflectionMethod($widget, $action);
        } catch (\ReflectionException) {
            return false;
        }

        return $method->isPublic();
    }
}
