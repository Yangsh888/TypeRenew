<?php

namespace Typecho\I18n;

class GetTextMulti
{
    private array $handlers = [];

    public function __construct(string $fileName)
    {
        $this->addFile($fileName);
    }

    public function addFile(string $fileName)
    {
        $this->handlers[] = new GetText($fileName, true);
    }

    public function translate(string $string): string
    {
        $count = -1;

        foreach ($this->handlers as $handle) {
            $translated = $handle->translate($string, $count);
            if (- 1 != $count) {
                return $translated;
            }
        }

        return $string;
    }

    public function ngettext(string $single, string $plural, int $number): string
    {
        $count = - 1;

        foreach ($this->handlers as $handler) {
            $string = $handler->ngettext($single, $plural, $number, $count);
            if (- 1 != $count) {
                return $string;
            }
        }

        return $number != 1 ? $plural : $single;
    }

    public function __destruct()
    {
        foreach ($this->handlers as $handler) {
            unset($handler);
        }
    }
}
