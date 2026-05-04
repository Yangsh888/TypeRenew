<?php

namespace Widget\XmlRpc;

use IXR\Exception;
use IXR\Hook;
use ReflectionMethod;
use Typecho\Widget;
use Widget\XmlRpc as XmlRpcWidget;

class HookHandler implements Hook
{
    private XmlRpcWidget $xmlRpc;
    private MethodRegistry $registry;

    public function __construct(XmlRpcWidget $xmlRpc, MethodRegistry $registry)
    {
        $this->xmlRpc = $xmlRpc;
        $this->registry = $registry;
    }

    /**
     * @param string $methodName
     * @param ReflectionMethod $reflectionMethod
     * @param array $parameters
     */
    public function beforeRpcCall(string $methodName, ReflectionMethod $reflectionMethod, array $parameters)
    {
        $valid = 2;
        $auth = [];

        foreach ($reflectionMethod->getParameters() as $key => $parameter) {
            $name = $parameter->getName();
            if (($name == 'userName' || $name == 'password') && array_key_exists($key, $parameters)) {
                $auth[$name] = (string) $parameters[$key];
                $valid--;
            }
        }

        if ($valid != 0) {
            return;
        }

        $user = $this->xmlRpc->userWidget();
        if (!$user->login($auth['userName'], $auth['password'], true)) {
            throw new Exception(_t('无法登录, 密码错误'), 403);
        }

        if (!$user->pass($this->registry->accessLevel($methodName), true)) {
            throw new Exception(_t('权限不足'), 403);
        }

        $user->execute();
    }

    /**
     * @param string $methodName
     * @param mixed $result
     */
    public function afterRpcCall(string $methodName, &$result): void
    {
        Widget::destroy();
    }
}
