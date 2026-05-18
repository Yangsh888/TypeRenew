<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Layout;

trait TextInputTrait
{
    public function input(?string $name = null, ?array $options = null): ?Layout
    {
        $input = new Layout('input', [
            'id' => $name . '-0-' . self::$uniqueId,
            'name' => $name,
            'type' => $this->getType(),
            'class' => 'text'
        ]);

        $this->container($input);
        $this->inputs[] = $input;

        if (isset($this->label)) {
            $this->label->setAttribute('for', $name . '-0-' . self::$uniqueId);
        }

        return $input;
    }

    protected function inputValue($value)
    {
        if (isset($value)) {
            $this->input->setAttribute('value', $this->filterValue($value));
        } else {
            $this->input->removeAttribute('value');
        }
    }

    abstract protected function filterValue(string $value): string;

    abstract protected function getType(): string;
}
