<?php

namespace Typecho\Widget\Helper;

use Typecho\Widget\Exception;

abstract class PageNavigator
{
    protected int $totalPage;

    protected string $anchor = '';

    protected $pageHolder = ['{page}', '%7Bpage%7D'];

    public function __construct(
        protected int $total,
        protected int $currentPage,
        protected int $pageSize,
        protected string $pageTemplate
    ) {
        $this->totalPage = ceil($total / $pageSize);

        if (($currentPage > $this->totalPage || $currentPage < 1) && $total > 0) {
            throw new Exception('Page Not Exists', 404);
        }
    }

    public function setPageHolder(string $holder)
    {
        $this->pageHolder = ['{' . $holder . '}',
            str_replace(['{', '}'], ['%7B', '%7D'], $holder)];
    }

    public function setAnchor(string $anchor)
    {
        $this->anchor = '#' . $anchor;
    }

    public function render()
    {
        throw new Exception('Method Not Implemented', 500);
    }
}
