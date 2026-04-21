<?php

namespace Typecho;

use Typecho\Widget\Helper\EmptyClass;
use Typecho\Widget\Request as WidgetRequest;
use Typecho\Widget\Response as WidgetResponse;
use Typecho\Widget\Terminal;

/**
 * 未知属性通过 __get/__set 映射到 $row，兼容旧式 Widget 行数据访问。
 */
#[\AllowDynamicProperties]
abstract class Widget
{
    private static array $widgetPool = [];
    private static array $widgetAlias = [];
    protected WidgetRequest $request;
    protected WidgetResponse $response;
    protected array $stack = [];
    protected int $sequence = 0;
    protected int $length = 0;
    protected Config $parameter;
    protected array $row = [];

    public function __construct(WidgetRequest $request, WidgetResponse $response, $params = null)
    {
        $this->request = $request;
        $this->response = $response;
        $this->parameter = Config::factory($params);
        $this->init();
    }

    protected function init()
    {
    }

    public static function alias(string $widgetClass, string $aliasClass)
    {
        self::$widgetAlias[$widgetClass] = $aliasClass;
    }

    public static function widget(
        string $alias,
        $params = null,
        $request = null,
        $disableSandboxOrCallback = true
    ): Widget {
        [$className] = explode('@', $alias);
        $key = Common::nativeClassName($alias);

        if (isset(self::$widgetAlias[$className])) {
            $className = self::$widgetAlias[$className];
        }

        $sandbox = false;

        if ($disableSandboxOrCallback === false || is_callable($disableSandboxOrCallback)) {
            $sandbox = true;
            Request::getInstance()->beginSandbox(new Config($request));
            Response::getInstance()->beginSandbox();
        }

        if ($sandbox || !isset(self::$widgetPool[$key])) {
            $requestObject = new WidgetRequest(Request::getInstance(), isset($request) ? new Config($request) : null);
            $responseObject = new WidgetResponse(Request::getInstance(), Response::getInstance());

            try {
                $widget = new $className($requestObject, $responseObject, $params);
                $widget->execute();

                if ($sandbox && is_callable($disableSandboxOrCallback)) {
                    call_user_func($disableSandboxOrCallback, $widget);
                }
            } catch (Terminal $e) {
                $widget = $widget ?? null;
            } finally {
                if ($sandbox) {
                    Response::getInstance()->endSandbox();
                    Request::getInstance()->endSandbox();
                    return $widget;
                }
            }

            self::$widgetPool[$key] = $widget;
        }

        return self::$widgetPool[$key];
    }

    public static function alloc($params = null, $request = null, $disableSandboxOrCallback = true): Widget
    {
        return self::widget(static::class, $params, $request, $disableSandboxOrCallback);
    }

    public static function allocWithAlias(
        ?string $alias,
        $params = null,
        $request = null,
        $disableSandboxOrCallback = true
    ): Widget {
        return self::widget(
            static::class . (isset($alias) ? '@' . $alias : ''),
            $params,
            $request,
            $disableSandboxOrCallback
        );
    }

    public static function destroy(?string $alias = null): void
    {
        if (Common::nativeClassName(static::class) == 'Typecho_Widget') {
            if (isset($alias)) {
                unset(self::$widgetPool[$alias]);
            } else {
                self::$widgetPool = [];
            }
        } else {
            $alias = static::class . (isset($alias) ? '@' . $alias : '');
            unset(self::$widgetPool[$alias]);
        }
    }

    public function execute()
    {
    }

    public function on(bool $condition)
    {
        if ($condition) {
            return $this;
        } else {
            return new EmptyClass();
        }
    }

    public function to(&$variable): Widget
    {
        return $variable = $this;
    }

    public function template(string $template): string
    {
        return preg_replace_callback(
            "/\{([_a-z0-9]+)\}/i",
            function (array $matches) {
                return $this->{$matches[1]};
            },
            $template
        );
    }

    public function parse(string $template)
    {
        while ($this->next()) {
            echo $this->template($template);
        }
    }

    public function toColumn(string|array $column): mixed
    {
        if (is_array($column)) {
            $item = [];
            foreach ($column as $key) {
                $item[$key] = $this->{$key};
            }
            return $item;
        } else {
            return $this->{$column};
        }
    }

    public function toArray(string|array $column): array
    {
        $result = [];
        while ($this->next()) {
            $result[] = $this->toColumn($column);
        }
        return $result;
    }

    public function next()
    {
        $key = key($this->stack);

        if ($key !== null && isset($this->stack[$key])) {
            $this->row = current($this->stack);
            next($this->stack);
            $this->sequence++;
        } else {
            reset($this->stack);
            $this->sequence = 0;
            return false;
        }

        return $this->row;
    }

    public function push(array $value)
    {
        $this->row = $value;
        $this->length++;
        $this->stack[] = $value;
        return $value;
    }

    public function pushAll(array $values)
    {
        foreach ($values as $value) {
            $this->push($value);
        }
    }

    public function alt(...$args)
    {
        $this->altBy($this->sequence, ...$args);
    }

    public function altBy(int $current, ...$args)
    {
        $num = count($args);
        $split = $current % $num;
        echo $args[(0 == $split ? $num : $split) - 1];
    }

    public function have(): bool
    {
        return !empty($this->stack);
    }

    public function __call(string $name, array $args)
    {
        $method = 'call' . ucfirst($name);
        self::pluginHandle()->trigger($plugged)->call($method, $this, $args);

        if (!$plugged) {
            echo $this->{$name};
        }
    }

    public static function pluginHandle(): Plugin
    {
        return Plugin::factory(static::class);
    }

    public function __get(string $name)
    {
        $method = '___' . $name;
        $key = '#' . $name;

        if (array_key_exists($key, $this->row)) {
            return $this->row[$key];
        } elseif (method_exists($this, $method)) {
            $this->row[$key] = $this->$method();
            return $this->row[$key];
        } elseif (array_key_exists($name, $this->row)) {
            return $this->row[$name];
        } else {
            $return = self::pluginHandle()->trigger($plugged)->call($method, $this);
            if ($plugged) {
                return $return;
            }
        }

        return null;
    }

    public function __set(string $name, $value)
    {
        $method = '___' . $name;
        $key = '#' . $name;

        if (isset($this->row[$key]) || method_exists($this, $method)) {
            $this->row[$key] = $value;
        } else {
            $this->row[$name] = $value;
        }
    }

    public function __isset(string $name)
    {
        $method = '___' . $name;
        $key = '#' . $name;

        return isset($this->row[$key]) || method_exists($this, $method) || isset($this->row[$name]);
    }

    public function ___sequence(): int
    {
        return $this->sequence;
    }

    public function ___length(): int
    {
        return $this->length;
    }

    public function ___request(): WidgetRequest
    {
        return $this->request;
    }

    public function ___response(): WidgetResponse
    {
        return $this->response;
    }

    public function ___parameter(): Config
    {
        return $this->parameter;
    }
}
