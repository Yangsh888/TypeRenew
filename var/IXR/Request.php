<?php

namespace IXR;

class Request
{
    private string $xml;

    public function __construct(string $method, array $args)
    {
        $escapedMethod = htmlspecialchars(
            $method,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1,
            'UTF-8'
        );
        $escapedMethod = preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $escapedMethod
        ) ?? '';

        $this->xml = <<<EOD
<?xml version="1.0"?>
<methodCall>
<methodName>{$escapedMethod}</methodName>
<params>

EOD;
        foreach ($args as $arg) {
            $this->xml .= '<param><value>';
            $v = new Value($arg);
            $this->xml .= $v->getXml();
            $this->xml .= "</value></param>\n";
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
