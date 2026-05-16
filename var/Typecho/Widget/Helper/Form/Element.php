<?php

namespace Typecho\Widget\Helper\Form;

use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

abstract class Element extends Layout
{
    protected static int $uniqueId = 0;

    public Layout $container;

    public ?Layout $input;

    public array $inputs = [];

    public Layout $label;

    public array $rules = [];

    public ?string $name;

    public $value;

    protected Layout $description;

    protected Layout $message;

    protected array $multiline = [];

    /**
     * 构造函数
     *
     * @param string|null $name 表单输入项名称
     * @param array|null $options 选择项
     * @param mixed $value 表单默认值
     * @param string|null $label 表单标题
     * @param string|null $description 表单描述
     * @return void
     */
    public function __construct(
        ?string $name = null,
        ?array $options = null,
        $value = null,
        ?string $label = null,
        ?string $description = null
    ) {
        parent::__construct(
            'ul',
            ['class' => 'typecho-option', 'id' => 'typecho-option-item-' . $name . '-' . self::$uniqueId]
        );

        $this->name = $name;
        self::$uniqueId++;

        $this->init();

        if (null !== $label) {
            $this->label($label);
        }

        $this->input = $this->input($name, $options);

        if (null !== $value) {
            $this->value($value);
        }

        if (null !== $description) {
            $this->description($description);
        }
    }

    /**
     * 供子类覆写的初始化钩子
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * 创建表单标题
     *
     * @param string $value 标题字符串
     */
    public function label(string $value): Element
    {
        if (empty($this->label)) {
            $this->label = new Layout('label', ['class' => 'typecho-label']);
            $this->container($this->label);
        }

        $this->label->html($value);
        return $this;
    }

    /**
     * 在容器里增加元素
     *
     * @param Layout $item 表单元素
     */
    public function container(Layout $item): Element
    {
        if (empty($this->container)) {
            $this->container = new Layout('li');
            $this->addItem($this->container);
        }

        $this->container->addItem($item);
        return $this;
    }

    /**
     * 初始化当前输入项
     *
     * @param string|null $name 表单元素名称
     * @param array|null $options 选择项
     * @return Layout|null
     */
    abstract public function input(?string $name = null, ?array $options = null): ?Layout;

    /**
     * 设置表单元素值
     *
     * @param mixed $value 表单元素值
     * @return Element
     */
    public function value($value): Element
    {
        $this->value = $value;
        $this->inputValue($value);
        return $this;
    }

    /**
     * 设置描述信息
     *
     * @param string $description 描述信息
     * @return Element
     */
    public function description(string $description): Element
    {
        if (empty($this->description)) {
            $this->description = new Layout('p', ['class' => 'description']);
            $this->container($this->description);
        }

        $this->description->html($description);
        return $this;
    }

    /**
     * 设置提示信息
     *
     * @param string $message 提示信息
     * @return Element
     */
    public function message(string $message): Element
    {
        if (empty($this->message)) {
            $this->message = new Layout('p', ['class' => 'message error']);
            $this->container($this->message);
        }

        $this->message->html($message);
        return $this;
    }

    /**
     * 多行输出模式
     *
     * @return Layout
     */
    public function multiline(): Layout
    {
        $item = new Layout('span');
        $this->multiline[] = $item;
        return $item;
    }

    /**
     * 多行输出模式
     *
     * @return Element
     */
    public function multiMode(): Element
    {
        foreach ($this->multiline as $item) {
            $item->setAttribute('class', 'multiline');
        }
        return $this;
    }

    /**
     * 增加验证器
     *
     * @param mixed ...$rules
     */
    public function addRule(...$rules): Element
    {
        $this->rules[] = $rules;
        return $this;
    }

    /**
     * 统一设置所有输入项的属性值
     *
     * @param string $attributeName
     * @param mixed $attributeValue
     */
    public function setInputsAttribute(string $attributeName, $attributeValue)
    {
        foreach ($this->inputs as $input) {
            $input->setAttribute($attributeName, $attributeValue);
        }
    }

    /**
     * 设置表单元素值
     *
     * @param mixed $value 表单元素值
     */
    abstract protected function inputValue($value);

    protected function filterValue(string $value): string
    {
        if (preg_match_all('/[_0-9a-z-]+/i', $value, $matches)) {
            return implode('-', $matches[0]);
        }

        return '';
    }
}
