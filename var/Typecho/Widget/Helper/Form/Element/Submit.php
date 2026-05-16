<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Submit extends Element
{
    public function input(?string $name = null, ?array $options = null): ?Layout
    {
        $this->setAttribute('class', 'typecho-option typecho-option-submit');
        $input = new Layout('button', ['type' => 'submit']);
        $this->container($input);
        $this->inputs[] = $input;

        return $input;
    }

    /**
     * 设置表单元素值
     *
     * @param mixed $value 表单元素值
     */
    protected function inputValue($value)
    {
        $this->input->html($value ?? 'Submit');
    }
}
