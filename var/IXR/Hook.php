<?php

namespace IXR;

use ReflectionMethod;

interface Hook
{
    public function beforeRpcCall(string $methodName, ReflectionMethod $reflectionMethod, array $parameters);

    public function afterRpcCall(string $methodName, &$result): void;
}
