<?php

namespace Typecho\Widget\Helper;

use Typecho\Widget\Exception;

abstract class PageNavigator
{
    protected int $total;

    protected int $totalPage;

    protected int $currentPage;

    protected int $pageSize;

    protected string $pageTemplate;

    protected string $anchor = '';

    protected $pageHolder = ['{page}', '%7Bpage%7D'];

    /**
     * 构造函数,初始化页面基本信息
     *
     * @param integer $total 记录总数
     * @param integer $currentPage 当前页面
     * @param integer $pageSize 每页记录数
     * @param string $pageTemplate 页面链接模板
     * @throws Exception
     */
    public function __construct(int $total, int $currentPage, int $pageSize, string $pageTemplate)
    {
        $this->total = $total;
        $this->totalPage = ceil($total / $pageSize);
        $this->currentPage = $currentPage;
        $this->pageSize = $pageSize;
        $this->pageTemplate = $pageTemplate;

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
