<?php

namespace IXR;

/**
 * IXR消息
 *
 * @package IXR
 */
class Message
{
    public const ERROR_XML_EXTENSION = 'server error. PHP XML extension is required';
    private const MAX_NESTING = 32;
    private const MAX_PARAMS = 64;

    /**
     * @var string
     */
    public string $message;

    /**
     * @var string
     */
    public string $messageType = '';  // methodCall / methodResponse / fault

    public int $faultCode = 0;

    public string $faultString = '';

    public string $parseError = '';

    /**
     * @var string
     */
    public string $methodName = '';

    /**
     * @var array
     */
    public array $params = [];

    // Current variable stacks
    private array $arrayStructs = [];   // The stack used to keep track of the current array/struct

    private array $arrayStructsTypes = []; // Stack keeping track of if things are structs or array

    private array $currentStructName = [];  // A stack as well

    private string $currentTagContents = '';

    /**
     * @param string $message
     */
    public function __construct(string $message)
    {
        $this->message = $message;
    }

    /**
     * @return bool
     */
    public function parse(): bool
    {
        $this->parseError = '';
        $this->messageType = '';
        $this->faultCode = 0;
        $this->faultString = '';
        $this->methodName = '';
        $this->params = [];
        $this->arrayStructs = [];
        $this->arrayStructsTypes = [];
        $this->currentStructName = [];
        $this->currentTagContents = '';
        $this->message = preg_replace('/<\?xml(.*)?\?' . '>/', '', $this->message);
        if (trim($this->message) == '') {
            $this->parseError = 'parse error. empty document';
            return false;
        }

        $count = 0;
        while (false !== ($pos = strpos($this->message, '<!DOCTYPE'))) {
            if ($count >= 10) {
                $this->parseError = 'parse error. invalid doctype';
                return false;
            }

            $this->message = substr($this->message, 0, $pos)
                . substr($this->message, strpos($this->message, '>', $pos) + 1);
            $count++;
        }

        if (!function_exists('xml_parser_create')) {
            $this->parseError = self::ERROR_XML_EXTENSION;
            return false;
        }

        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_object($parser, $this);
        xml_set_element_handler($parser, [$this, 'tagOpen'], [$this, 'tagClose']);
        xml_set_character_data_handler($parser, [$this, 'cdata']);
        if (!xml_parse($parser, $this->message)) {
            $code = xml_get_error_code($parser);
            $line = xml_get_current_line_number($parser);
            $detail = xml_error_string($code);
            $this->parseError = 'parse error. ' . strtolower($detail) . ($line > 0 ? ' at line ' . $line : '');
            xml_parser_free($parser);
            return false;
        }
        xml_parser_free($parser);

        if ($this->parseError !== '') {
            return false;
        }

        if ($this->messageType === '') {
            $this->parseError = 'parse error. invalid xml-rpc request';
            return false;
        }

        if ($this->messageType === 'fault') {
            if (!isset($this->params[0]) || !is_array($this->params[0])) {
                $this->parseError = 'parse error. invalid xml-rpc fault';
                return false;
            }

            $fault = $this->params[0];
            $this->faultCode = (int) ($fault['faultCode'] ?? 0);
            $this->faultString = (string) ($fault['faultString'] ?? '');
        }
        return true;
    }

    /**
     * @param $parser
     * @param string $tag
     * @param $attr
     */
    private function tagOpen($parser, string $tag, $attr)
    {
        if ($this->parseError !== '') {
            return;
        }

        switch ($tag) {
            case 'methodCall':
            case 'methodResponse':
            case 'fault':
                $this->messageType = $tag;
                break;
            /* Deal with stacks of arrays and structs */
            case 'data':    // data is to all intents and puposes more interesting than array
                $this->arrayStructsTypes[] = 'array';
                $this->arrayStructs[] = [];
                if (count($this->arrayStructs) > self::MAX_NESTING) {
                    $this->parseError = 'parse error. nesting too deep';
                    return;
                }
                break;
            case 'struct':
                $this->arrayStructsTypes[] = 'struct';
                $this->arrayStructs[] = [];
                if (count($this->arrayStructs) > self::MAX_NESTING) {
                    $this->parseError = 'parse error. nesting too deep';
                    return;
                }
                break;
        }
    }

    /**
     * @param $parser
     * @param string $cdata
     */
    private function cdata($parser, string $cdata)
    {
        if ($this->parseError !== '') {
            return;
        }

        $this->currentTagContents .= $cdata;
    }

    /**
     * @param $parser
     * @param string $tag
     */
    private function tagClose($parser, string $tag)
    {
        if ($this->parseError !== '') {
            return;
        }

        switch ($tag) {
            case 'int':
            case 'i4':
                $value = (int) trim($this->currentTagContents);
                $this->currentTagContents = '';
                break;
            case 'double':
                $value = (double) trim($this->currentTagContents);
                $this->currentTagContents = '';
                break;
            case 'string':
                $value = $this->currentTagContents;
                $this->currentTagContents = '';
                break;
            case 'dateTime.iso8601':
                $value = new Date(trim($this->currentTagContents));
                $this->currentTagContents = '';
                break;
            case 'value':
                // "If no type is indicated, the type is string."
                if ($this->currentTagContents !== '') {
                    $value = $this->currentTagContents;
                    $this->currentTagContents = '';
                }
                break;
            case 'boolean':
                $raw = trim($this->currentTagContents);
                if ($raw !== '0' && $raw !== '1') {
                    $this->parseError = 'parse error. invalid boolean value';
                    $this->currentTagContents = '';
                    return;
                }
                $value = $raw === '1';
                $this->currentTagContents = '';
                break;
            case 'base64':
                $value = base64_decode($this->currentTagContents, true);
                if ($value === false) {
                    $this->parseError = 'parse error. invalid base64 payload';
                    $this->currentTagContents = '';
                    return;
                }
                $this->currentTagContents = '';
                break;
            /* Deal with stacks of arrays and structs */
            case 'data':
            case 'struct':
                $value = array_pop($this->arrayStructs);
                array_pop($this->arrayStructsTypes);
                break;
            case 'member':
                array_pop($this->currentStructName);
                break;
            case 'name':
                $this->currentStructName[] = trim($this->currentTagContents);
                $this->currentTagContents = '';
                break;
            case 'methodName':
                $this->methodName = trim($this->currentTagContents);
                $this->currentTagContents = '';
                break;
        }
        if (isset($value)) {
            if (count($this->arrayStructs) > 0) {
                if ($this->arrayStructsTypes[count($this->arrayStructsTypes) - 1] == 'struct') {
                    if ($this->currentStructName === []) {
                        $this->parseError = 'parse error. invalid struct member';
                        return;
                    }
                    $this->arrayStructs[count($this->arrayStructs) - 1]
                        [$this->currentStructName[count($this->currentStructName) - 1]] = $value;
                } else {
                    $this->arrayStructs[count($this->arrayStructs) - 1][] = $value;
                }
            } else {
                if (count($this->params) >= self::MAX_PARAMS) {
                    $this->parseError = 'parse error. too many params';
                    return;
                }
                $this->params[] = $value;
            }
        }
    }
}
