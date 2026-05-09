<?php

namespace Typecho\Widget\Helper;

use Typecho\Cookie;
use Typecho\Common;
use Typecho\Request;
use Typecho\Validate;
use Typecho\Widget\Helper\Form\Element;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Form extends Layout
{
    public const POST_METHOD = 'post';

    public const GET_METHOD = 'get';

    public const STANDARD_ENCODE = 'application/x-www-form-urlencoded';

    public const MULTIPART_ENCODE = 'multipart/form-data';

    public const TEXT_ENCODE = 'text/plain';

    private array $inputs = [];

    public function __construct($action = null, $method = self::GET_METHOD, $enctype = self::STANDARD_ENCODE)
    {
        parent::__construct('form');

        $this->setClose(false);

        $this->setAction($action);
        $this->setMethod($method);
        $this->setEncodeType($enctype);
    }

    public function setAction(?string $action): Form
    {
        $this->setAttribute('action', $action);
        return $this;
    }

    public function setMethod(string $method): Form
    {
        $this->setAttribute('method', $method);
        return $this;
    }

    public function setEncodeType(string $enctype): Form
    {
        $this->setAttribute('enctype', $enctype);
        return $this;
    }

    public function addInput(Element $input): Form
    {
        $this->inputs[$input->name] = $input;
        $this->addItem($input);
        return $this;
    }

    public function getInput(string $name)
    {
        return $this->inputs[$name] ?? null;
    }

    public function getAllRequest(): array
    {
        return $this->getParams(array_keys($this->inputs));
    }

    public function getValues(): array
    {
        $values = [];

        foreach ($this->inputs as $name => $input) {
            $values[$name] = $input->value;
        }
        return $values;
    }

    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function validate(): array
    {
        $validator = new Validate();
        $rules = [];

        foreach ($this->inputs as $name => $input) {
            $rules[$name] = $input->rules;
        }

        $id = md5(implode('"', array_keys($this->inputs)));

        $formData = $this->getParams(array_keys($rules));
        $error = $validator->run($formData, $rules);

        if ($error) {
            Cookie::set('__typecho_form_message_' . $id, Common::jsonEncode($error, 0, '{}'));

            Cookie::set('__typecho_form_record_' . $id, Common::jsonEncode($formData, 0, '{}'));
        }

        return $error;
    }

    public function getParams(array $params): array
    {
        $result = [];
        $request = Request::getInstance();
        $getter = strtolower((string) $this->getAttribute('method')) === self::POST_METHOD ? 'getInput' : 'get';

        foreach ($params as $param) {
            $input = $this->getInput($param);
            $result[$param] = $request->$getter($param, is_array($input?->value ?? null) ? [] : null);
        }

        return $result;
    }

    public function render()
    {
        $id = md5(implode('"', array_keys($this->inputs)));
        $record = Cookie::get('__typecho_form_record_' . $id);
        $message = Cookie::get('__typecho_form_message_' . $id);

        if (!empty($record)) {
            $record = json_decode($record, true);
            $message = json_decode($message, true);
            foreach ($this->inputs as $name => $input) {
                $input->value($record[$name] ?? $input->value);

                if (isset($message[$name])) {
                    $input->message($message[$name]);
                }
            }

            Cookie::delete('__typecho_form_record_' . $id);
        }

        parent::render();
        Cookie::delete('__typecho_form_message_' . $id);
    }
}
