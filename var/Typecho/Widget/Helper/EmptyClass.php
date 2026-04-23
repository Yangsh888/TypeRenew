<?php

namespace Typecho\Widget\Helper;

class EmptyClass
{
    public function __call(string $name, array $args)
    {
        return $this;
    }
}
