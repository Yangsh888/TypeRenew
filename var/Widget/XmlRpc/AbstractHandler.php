<?php

namespace Widget\XmlRpc;

use Widget\XmlRpc as XmlRpcWidget;

abstract class AbstractHandler
{
    protected XmlRpcWidget $xmlRpc;

    public function __construct(XmlRpcWidget $xmlRpc)
    {
        $this->xmlRpc = $xmlRpc;
    }
}
