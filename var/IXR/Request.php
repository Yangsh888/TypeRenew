<?php

namespace IXR;

class Request
{
    private string $xml;

    public function __construct(string $method, array $args)
    {
        $this->xml = '<?xml version="1.0"?>' . "\n"
            . '<methodCall><methodName>' . Value::escape($method) . '</methodName><params>' . "\n";

        foreach ($args as $arg) {
            $v = new Value($arg);
            $this->xml .= '<param><value>' . $v->getXml() . "</value></param>\n";
        }

        $this->xml .= '</params></methodCall>';
    }

    public function getLength(): int
    {
        return strlen($this->xml);
    }

    public function getXml(): string
    {
        return $this->xml;
    }
}
