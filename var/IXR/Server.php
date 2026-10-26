<?php

namespace IXR;

use Typecho\Widget\Exception as WidgetException;

class Server
{
    public const MAX_REQUEST_SIZE = 10485760;

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
        } catch (\Throwable $e) {
            return new Error(
                -32001,
                'server error. requested class method "' . $methodName . '" failed.'
            );
        }
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

        switch ($name) {
            case 'int':
                $value = intval($value);
                return true;
            case 'float':
                $value = floatval($value);
                return true;
            case 'bool':
                $value = (bool) $value;
                return true;
            case 'string':
                if (is_array($value) || (is_object($value) && !method_exists($value, '__toString'))) {
                    return false;
                }
                $value = (string) $value;
                return true;
            case 'array':
                return is_array($value);
            case 'object':
                return is_object($value);
            case 'callable':
                return is_callable($value);
            case 'iterable':
                return is_iterable($value);
            case 'null':
                return $value === null;
            default:
                return true;
        }
    }

    private function error($error, ?string $message = null): string
    {
        if (!$error instanceof Error) {
            $error = new Error($error, $message);
        }

        return $error->getXml();
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

    public function handle(string $xml): string
    {
        if (strlen($xml) > self::MAX_REQUEST_SIZE) {
            return $this->error(-32700, 'parse error. request too large');
        }

        $message = new Message($xml);

        if (!$message->parse()) {
            return $this->error(-32700, 'parse error. not well formed');
        } elseif ($message->messageType != 'methodCall') {
            return $this->error(
                -32600,
                'server error. invalid xml-rpc. not conforming to spec. Request must be a methodCall'
            );
        }

        $result = $this->call($message->methodName, $message->params);

        if ($result instanceof Error) {
            return $this->error($result);
        }

        $r = new Value($result);
        $resultXml = $r->getXml();

        return <<<EOD
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
    }

    public function serve()
    {
        $xml = file_get_contents('php://input', false, null, 0, self::MAX_REQUEST_SIZE + 1);
        $this->output($this->handle($xml ?: ''));
    }
}
