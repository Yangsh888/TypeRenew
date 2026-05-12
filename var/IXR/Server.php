<?php

namespace IXR;

use Typecho\Widget\Exception as WidgetException;

/**
 * IXR服务器
 *
 * @package IXR
 */
class Server
{
    private const DEFAULT_MAX_REQUEST_BYTES = 16777216;

    /**
     * 回调函数
     *
     * @var array
     */
    private array $callbacks;

    /**
     * 默认参数
     *
     * @var array
     */
    private array $capabilities;

    /**
     * @var Hook
     */
    private Hook $hook;

    /**
     * 构造函数
     *
     * @param array $callbacks 回调函数
     */
    public function __construct(
        array $callbacks = [],
        bool $allowListMethods = true,
        bool $allowMulticall = true
    )
    {
        $this->setCapabilities($allowMulticall);
        $this->callbacks = $callbacks;
        $this->setCallbacks($allowListMethods, $allowMulticall);
    }

    /**
     * 获取默认参数
     * @return array
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * 列出所有方法
     * @return array
     */
    public function listMethods(): array
    {
        return array_reverse(array_keys($this->callbacks));
    }

    /**
     * 一次处理多个请求
     *
     * @param array $methodcalls
     * @return array
     */
    public function multiCall(array $methodcalls): array
    {
        $return = [];
        foreach ($methodcalls as $call) {
            $method = $call['methodName'];
            $params = $call['params'];
            if ($method == 'system.multicall') {
                $result = new Error(-32600, 'Recursive calls to system.multicall are forbidden');
            } else {
                $result = $this->call($method, $params);
            }
            if ($result instanceof Error) {
                $return[] = [
                    'faultCode'   => $result->code,
                    'faultString' => $result->message
                ];
            } else {
                $return[] = [$result];
            }
        }
        return $return;
    }

    /**
     * @param string $methodName
     * @return string|Error
     */
    public function methodHelp(string $methodName)
    {
        if (!$this->hasMethod($methodName)) {
            return new Error(-32601, 'server error. requested method ' . $methodName . ' does not exist.');
        }

        [$object, $method] = $this->callbacks[$methodName];

        try {
            $ref = new \ReflectionMethod($object, $method);
            $doc = $ref->getDocComment();

            return $doc ?: '';
        } catch (\ReflectionException $e) {
            return '';
        }
    }

    /**
     * @param Hook $hook
     */
    public function setHook(Hook $hook)
    {
        $this->hook = $hook;
    }

    /**
     * 呼叫内部方法
     *
     * @param string $methodName 方法名
     * @param array $args 参数
     * @return mixed
     */
    private function call(string $methodName, array $args)
    {
        if (!$this->isPositionalArgs($args)) {
            return new Error(-32602, 'server error. requested class method "' . $methodName . '" params must be positional.');
        }

        if (!$this->hasMethod($methodName)) {
            return new Error(-32601, 'server error. requested method ' . $methodName . ' does not exist.');
        }
        $method = $this->callbacks[$methodName];

        if (!is_callable($method)) {
            return new Error(
                -32601,
                'server error. requested class method "' . $methodName . '" does not exist.'
            );
        }

        [$object, $objectMethod] = $method;

        try {
            $ref = new \ReflectionMethod($object, $objectMethod);
            $requiredArgs = $ref->getNumberOfRequiredParameters();
            if (count($args) < $requiredArgs) {
                return new Error(
                    -32602,
                    'server error. requested class method "' . $methodName . '" require ' . $requiredArgs . ' params.'
                );
            }

            foreach ($ref->getParameters() as $key => $parameter) {
                if (!array_key_exists($key, $args)) {
                    continue;
                }

                if ($parameter->hasType() && !$this->coerceArgument($args[$key], $parameter->getType())) {
                    return new Error(
                        -32602,
                        'server error. requested class method "'
                        . $methodName . '" ' . $key . ' param has wrong type.'
                    );
                }
            }

            if (isset($this->hook)) {
                $result = $this->hook->beforeRpcCall($methodName, $ref, $args);

                if (isset($result)) {
                    return $result;
                }
            }

            $result = $method(...$args);

            if (isset($this->hook)) {
                $this->hook->afterRpcCall($methodName, $result);
            }

            return $result;
        } catch (\ReflectionException $e) {
            return new Error(
                -32601,
                'server error. requested class method "' . $methodName . '" does not exist.'
            );
        } catch (Exception $e) {
            return new Error(
                $e->getCode(),
                $e->getMessage()
            );
        } catch (WidgetException $e) {
            return new Error(
                -32001,
                $e->getMessage()
            );
        } catch (\Exception $e) {
            return new Error(
                -32001,
                'server error. requested class method "' . $methodName . '" failed.'
            );
        } catch (\Throwable $e) {
            return new Error(
                -32001,
                'server error. requested class method "' . $methodName . '" failed.'
            );
        }
    }

    private function isPositionalArgs(array $args): bool
    {
        if ($args === []) {
            return true;
        }

        return array_keys($args) === range(0, count($args) - 1);
    }

    /**
     * 抛出错误
     * @param integer|Error $error 错误代码
     * @param string|null $message 错误消息
     * @return void
     */
    private function error($error, ?string $message = null)
    {
        // Accepts either an error object or an error code and message
        if (!$error instanceof Error) {
            $error = new Error($error, $message);
        }

        $this->output($error->getXml());
    }

    /**
     * 输出xml
     * @param string $xml 输出xml
     */
    private function output(string $xml)
    {
        $xml = '<?xml version="1.0"?>' . "\n" . $xml;
        $length = strlen($xml);
        header('Connection: close');
        header('Content-Length: ' . $length);
        header('Content-Type: text/xml');
        header('Date: ' . date('r'));
        echo $xml;
        exit;
    }

    private function coerceArgument(&$value, \ReflectionType $type): bool
    {
        if ($type instanceof \ReflectionNamedType) {
            return $this->matchNamedType($value, $type);
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $candidateType) {
                $candidate = $value;
                if ($this->matchNamedType($candidate, $candidateType)) {
                    $value = $candidate;
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof \ReflectionIntersectionType) {
            if (!is_object($value)) {
                return false;
            }

            foreach ($type->getTypes() as $candidateType) {
                if (!$this->matchNamedType($value, $candidateType)) {
                    return false;
                }
            }

            return true;
        }

        return true;
    }

    private function matchNamedType(&$value, \ReflectionNamedType $type): bool
    {
        if ($value === null) {
            return $type->allowsNull();
        }

        $name = $type->getName();
        if ($name === 'mixed') {
            return true;
        }

        if (!$type->isBuiltin()) {
            return $value instanceof $name;
        }

        return match ($name) {
            'int' => $this->coerceInt($value),
            'float' => $this->coerceFloat($value),
            'bool' => $this->coerceBool($value),
            'string' => !is_array($value) && (!is_object($value) || method_exists($value, '__toString'))
                && settype($value, 'string'),
            'array' => is_array($value),
            'object' => is_object($value),
            'callable' => is_callable($value),
            'iterable' => is_iterable($value),
            'null' => $value === null,
            default => true,
        };
    }

    private function coerceInt(&$value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value) {
            $value = (int) $value;
            return true;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            $value = (int) $value;
            return true;
        }

        return false;
    }

    private function coerceFloat(&$value): bool
    {
        if (is_float($value)) {
            return true;
        }

        if (is_int($value)) {
            $value = (float) $value;
            return true;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            $value = (float) $value;
            return true;
        }

        return false;
    }

    private function coerceBool(&$value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            $value = (bool) $value;
            return true;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['0', '1', 'true', 'false'], true)) {
                $value = in_array($normalized, ['1', 'true'], true);
                return true;
            }
        }

        return false;
    }

    /**
     * 是否存在方法
     * @param string $method 方法名
     * @return bool
     */
    private function hasMethod(string $method): bool
    {
        return isset($this->callbacks[$method]);
    }

    private function getMaxRequestBytes(): int
    {
        $postMaxSize = $this->parseIniBytes(function_exists('ini_get') ? (string) ini_get('post_max_size') : '');
        $uploadMaxSize = $this->parseIniBytes(function_exists('ini_get') ? (string) ini_get('upload_max_filesize') : '');
        $limit = max($postMaxSize, $uploadMaxSize);

        return $limit > 0 ? $limit : self::DEFAULT_MAX_REQUEST_BYTES;
    }

    private function parseIniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = is_numeric($unit) ? (float) $value : (float) substr($value, 0, -1);

        if ($number <= 0) {
            return 0;
        }

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }

    /**
     * 设置默认参数
     * @return void
     */
    private function setCapabilities(bool $allowMulticall)
    {
        // Initialises capabilities array
        $this->capabilities = [
            'xmlrpc'           => [
                'specUrl'     => 'http://www.xmlrpc.com/spec',
                'specVersion' => 1
            ],
            'faults_interop'   => [
                'specUrl'     => 'http://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php',
                'specVersion' => 20010516
            ],
        ];

        if ($allowMulticall) {
            $this->capabilities['system.multicall'] = [
                'specUrl'     => 'http://www.xmlrpc.com/discuss/msgReader$1208',
                'specVersion' => 1
            ];
        }
    }

    /**
     * 设置默认方法
     * @return void
     */
    private function setCallbacks(bool $allowListMethods, bool $allowMulticall)
    {
        $this->callbacks['system.getCapabilities'] = [$this, 'getCapabilities'];
        $this->callbacks['system.methodHelp'] = [$this, 'methodHelp'];

        if ($allowListMethods) {
            $this->callbacks['system.listMethods'] = [$this, 'listMethods'];
        }

        if ($allowMulticall) {
            $this->callbacks['system.multicall'] = [$this, 'multiCall'];
        }
    }

    /**
     * 服务入口
     */
    public function serve()
    {
        $payload = file_get_contents('php://input') ?: '';
        if (strlen($payload) > $this->getMaxRequestBytes()) {
            $this->error(-32600, 'server error. request entity too large.');
        }

        $message = new Message($payload);

        if (!$message->parse()) {
            $detail = $message->parseError !== '' ? $message->parseError : 'parse error. not well formed';
            $code = $message->parseError === Message::ERROR_XML_EXTENSION ? -32603 : -32700;
            $this->error($code, $detail);
        } elseif ($message->messageType != 'methodCall') {
            $this->error(-32600, 'server error. invalid xml-rpc. not conforming to spec. Request must be a methodCall');
        }

        $result = $this->call($message->methodName, $message->params);
        if ($result instanceof Error) {
            $this->error($result);
        }

        $r = new Value($result);
        $resultXml = $r->getXml();

        $xml = <<<EOD
<methodResponse>
  <params>
    <param>
      <value>
        $resultXml
      </value>
    </param>
  </params>
</methodResponse>

EOD;

        $this->output($xml);
    }
}
