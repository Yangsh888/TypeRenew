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
        if (!$this->registry->requiresAuth($methodName)) {
            return;
        }

        $auth = $this->resolveAuth($reflectionMethod, $parameters);
        if (!isset($auth['userName'], $auth['password'])) {
            throw new Exception(_t('XML-RPC 认证参数不完整'), 403);
        }

        $user = $this->xmlRpc->userWidget();
        if (!$user->login($auth['userName'], $auth['password'], true)) {
            sleep(2);
            throw new Exception(_t('无法登录, 密码错误'), 403);
        }

        $accessLevel = $this->registry->accessLevel($methodName);
        if ($accessLevel !== null && !$user->pass($accessLevel, true)) {
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

    /**
     * @return array{userName?: string, password?: string}
     */
    private function resolveAuth(ReflectionMethod $reflectionMethod, array $parameters): array
    {
        $auth = [];
        foreach ($reflectionMethod->getParameters() as $key => $parameter) {
            if (!array_key_exists($key, $parameters)) {
                continue;
            }

            $name = $parameter->getName();
            if ($name === 'userName' || $name === 'password') {
                $auth[$name] = (string) $parameters[$key];
            }
        }

        return $auth;
    }
}
