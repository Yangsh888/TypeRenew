<?php

namespace IXR;

class Error
{
    public int $code;

    public string $message;

    public function __construct(int $code, ?string $message = '')
    {
        $this->code = $code;
        $this->message = (string) $message;
    }

    public function getXml(): string
    {
        $message = Value::escape($this->message);

        return <<<EOD
<methodResponse>
  <fault>
    <value>
      <struct>
        <member>
          <name>faultCode</name>
          <value><int>{$this->code}</int></value>
        </member>
        <member>
          <name>faultString</name>
          <value><string>{$message}</string></value>
        </member>
      </struct>
    </value>
  </fault>
</methodResponse>
EOD;
    }
}
