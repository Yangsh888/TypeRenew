<?php

namespace IXR;

use Typecho\Widget\Exception as WidgetException;

#[\AllowDynamicProperties]
class Server
{
    private const DEFAULT_MAX_BODY_SIZE = 8388608;

    private array $callbacks;

    private array $capabilities;

    private Hook $hook;

    public function __construct(array $callbacks = [])
    {
        $this->setCapabilities();
        $this->callbacks = $callbacks;
        $this->callbacks['system.getCapabilities'] = [$this, 'getCapabilities'];
        $this->callbacks['system.listMethods'] = [$this, 'listMethods'];
        $this->callbacks['system.multicall'] = [$this, 'multiCall'];
        $this->callbacks['system.methodHelp'] = [$this, 'methodHelp'];
    }

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function listMethods(): array
    {
        return array_reverse(array_keys($this->callbacks));
    }

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

    public function setHook(Hook $hook)
    {
        $this->hook = $hook;
    }

    private function call(string $methodName, array $args)
    {
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
            if (!$this->isPositionalArgs($args)) {
                return new Error(
                    -32602,
                    'server error. requested class method "' . $methodName . '" params must be positional.'
                );
            }

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

            $result = call_user_func_array($method, $args);

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
        return $args === [] || array_keys($args) === range(0, count($args) - 1);
    }

    private function error($error, ?string $message = null)
    {
        if (!$error instanceof Error) {
            $error = new Error($error, $message);
        }

        $this->output($error->getXml());
    }

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

    private function maxBodySize(): int
    {
        $size = $this->parseIniSize((string) ini_get('post_max_size'));

        return $size >= 0 ? $size : self::DEFAULT_MAX_BODY_SIZE;
    }

    private function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        if (ctype_alpha($unit)) {
            $number = (float) substr($value, 0, -1);
        } else {
            $number = (float) $value;
            $unit = '';
        }

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
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
            'int' => settype($value, 'integer'),
            'float' => settype($value, 'double'),
            'bool' => settype($value, 'boolean'),
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

    private function hasMethod(string $method): bool
    {
        return isset($this->callbacks[$method]);
    }

    private function setCapabilities()
    {
        $this->capabilities = [
            'xmlrpc'           => [
                'specUrl'     => 'http://www.xmlrpc.com/spec',
                'specVersion' => 1
            ],
            'faults_interop'   => [
                'specUrl'     => 'http://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php',
                'specVersion' => 20010516
            ],
            'system.multicall' => [
                'specUrl'     => 'http://www.xmlrpc.com/discuss/msgReader$1208',
                'specVersion' => 1
            ],
        ];
    }

    public function serve()
    {
        if (!function_exists('xml_parser_create')) {
            $this->error(-32600, 'server error. XML extension is required.');
        }

        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        $maxBodySize = $this->maxBodySize();
        if ($maxBodySize > 0 && $contentLength > 0 && $contentLength > $maxBodySize) {
            $this->error(-32600, 'server error. request body is too large.');
        }

        $message = new Message(file_get_contents('php://input') ?: '');

        if (!$message->parse()) {
            $this->error(-32700, 'parse error. not well formed');
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
