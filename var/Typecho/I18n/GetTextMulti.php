<?php

namespace Typecho\I18n;

/**
 * 用于解决一个多个mo文件带来的读写问题
 * 我们重写了一个文件读取类
 *
 * @author qining
 * @category typecho
 * @package I18n
 */
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
        foreach ($this->handlers as $handle) {
            $string = $handle->translate($string, $count);
            if (- 1 != $count) {
                break;
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
                break;
            }
        }

        return $string;
    }

    public function __destruct()
    {
        foreach ($this->handlers as $handler) {
            unset($handler);
        }
    }
}
