<?php

namespace Typecho\Widget\Helper;

use Typecho\Cookie;
use Typecho\Request;
use Typecho\Validate;
use Typecho\Widget\Helper\Form\Element;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 表单处理帮手
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Form extends Layout
{
    /** 表单post方法 */
    public const POST_METHOD = 'post';

    /** 表单get方法 */
    public const GET_METHOD = 'get';

    /** 标准编码方法 */
    public const STANDARD_ENCODE = 'application/x-www-form-urlencoded';

    /** 混合编码 */
    public const MULTIPART_ENCODE = 'multipart/form-data';

    /** 文本编码 */
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

    /**
     * 设置表单提交目的
     *
     * @param string|null $action 表单提交目的
     * @return $this
     */
    public function setAction(?string $action): Form
    {
        $this->setAttribute('action', $action);
        return $this;
    }

    /**
     * 设置表单提交方法
     *
     * @param string $method 表单提交方法
     * @return $this
     */
    public function setMethod(string $method): Form
    {
        $this->setAttribute('method', $method);
        return $this;
    }

    /**
     * 设置表单编码方案
     *
     * @param string $enctype 编码方法
     * @return $this
     */
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

    /**
     * 获取输入项
     *
     * @param string $name 输入项名称
     * @return mixed
     */
    public function getInput(string $name)
    {
        return $this->inputs[$name];
    }

    /**
     * 获取所有输入项的提交值
     *
     * @return array
     */
    public function getAllRequest(): array
    {
        return $this->getParams(array_keys($this->inputs));
    }

    /**
     * 获取此表单的所有输入项固有值
     *
     * @return array
     */
    public function getValues(): array
    {
        $values = [];

        foreach ($this->inputs as $name => $input) {
            $values[$name] = $input->value;
        }
        return $values;
    }

    /**
     * 获取此表单的所有输入项
     *
     * @return array
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /**
     * 验证表单
     *
     * @return array
     */
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
            Cookie::set('__typecho_form_message_' . $id, json_encode($error));

            Cookie::set('__typecho_form_record_' . $id, json_encode($formData));
        }

        return $error;
    }

    /**
     * 获取提交数据源
     *
     * @param array $params 数据参数集
     * @return array
     */
    public function getParams(array $params): array
    {
        $result = [];
        $request = Request::getInstance();

        foreach ($params as $param) {
            $result[$param] = $request->get($param, is_array($this->getInput($param)->value) ? [] : null);
        }

        return $result;
    }

    /**
     * 显示表单
     *
     * @return void
     */
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
