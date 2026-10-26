<?php

namespace IXR;

class Value
{
    private $data;

    private ?string $type;

    public function __construct($data, ?string $type = null)
    {
        $this->data = $data;
        if (!$type) {
            $type = $this->calculateType();
        }
        $this->type = $type;
        if ($type == 'struct') {
            foreach ($this->data as $key => $value) {
                $this->data[$key] = new Value($value);
            }
        }
        if ($type == 'array') {
            for ($i = 0, $j = count($this->data); $i < $j; $i++) {
                $this->data[$i] = new Value($this->data[$i]);
            }
        }
    }

    public function getXml(): string
    {
        switch ($this->type) {
            case 'boolean':
                return '<boolean>' . (($this->data) ? '1' : '0') . '</boolean>';
            case 'int':
                return '<int>' . $this->data . '</int>';
            case 'double':
                return '<double>' . $this->data . '</double>';
            case 'string':
                return '<string>' . self::escape((string) $this->data) . '</string>';
            case 'array':
                $return = '<array><data>' . "\n";
                foreach ($this->data as $item) {
                    $return .= '  <value>' . $item->getXml() . "</value>\n";
                }
                $return .= '</data></array>';
                return $return;
            case 'struct':
                $return = '<struct>' . "\n";
                foreach ($this->data as $name => $value) {
                    $return .= '  <member><name>' . self::escape((string) $name) . '</name><value>';
                    $return .= $value->getXml() . "</value></member>\n";
                }
                $return .= '</struct>';
                return $return;
            case 'date':
            case 'base64':
                return $this->data->getXml();
        }

        return '<string>' . self::escape((string) $this->data) . '</string>';
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function calculateType(): string
    {
        if ($this->data === true || $this->data === false) {
            return 'boolean';
        }
        if (is_integer($this->data)) {
            return 'int';
        }
        if (is_double($this->data)) {
            return 'double';
        }
        if ($this->data instanceof Date) {
            return 'date';
        }
        if ($this->data instanceof Base64) {
            return 'base64';
        }
        if (is_object($this->data)) {
            $this->data = get_object_vars($this->data);
            return 'struct';
        }
        if (is_string($this->data) && !preg_match('//u', $this->data)) {
            $this->data = new Base64($this->data);
            return 'base64';
        }
        if (!is_array($this->data)) {
            return 'string';
        }

        return $this->isStruct($this->data) ? 'struct' : 'array';
    }

    private function isStruct($array): bool
    {
        $expected = 0;
        foreach ($array as $key => $value) {
            if ((string)$key != (string)$expected) {
                return true;
            }
            $expected++;
        }
        return false;
    }
}
