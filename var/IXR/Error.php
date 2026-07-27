<?php

namespace IXR;

class Error
{
    public int $code;

    public ?string $message;

    public function __construct(int $code, ?string $message = '')
    {
        $this->code = $code;
        $this->message = (string) $message;
    }

    public function getXml(): string
    {
        $message = htmlspecialchars(
            (string) $this->message,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1,
            'UTF-8'
        );
        $message = preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $message
        ) ?? '';

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
