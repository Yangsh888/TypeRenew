<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Textarea extends Element
{
    public function input(?string $name = null, ?array $options = null): ?Layout
    {
        $input = new Layout('textarea', ['id' => $name . '-0-' . self::$uniqueId, 'name' => $name]);
        $this->label->setAttribute('for', $name . '-0-' . self::$uniqueId);
        $this->container($input->setClose(false));
        $this->inputs[] = $input;

        return $input;
    }

    protected function inputValue($value)
    {
        $this->input->html(htmlspecialchars($value ?? ''));
    }
}
