<?php

namespace IXR;

/**
 * IXR Base64编码
 *
 * @package IXR
 */
#[\AllowDynamicProperties]
class Base64
{
    private string $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function getXml()
    {
        return '<base64>' . base64_encode($this->data) . '</base64>';
    }
}
