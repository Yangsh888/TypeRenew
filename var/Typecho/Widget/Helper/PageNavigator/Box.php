<?php

namespace Typecho\Widget\Helper\PageNavigator;

use Typecho\Widget\Helper\PageNavigator;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Box extends PageNavigator
{
    public function render(
        string $prevWord = 'PREV',
        string $nextWord = 'NEXT',
        int $splitPage = 3,
        string $splitWord = '...',
        array $template = []
    ) {
        if ($this->total < 1) {
            return;
        }

        $default = [
            'itemTag' => 'li',
            'textTag' => 'span',
            'currentClass' => 'current',
            'prevClass' => 'prev',
            'nextClass' => 'next'
        ];

        $template = array_merge($default, $template);

        $itemTag = $template['itemTag'];
        $textTag = $template['textTag'];
        $currentClass = $template['currentClass'];
        $prevClass = $template['prevClass'];
        $nextClass = $template['nextClass'];

        $itemBegin = empty($itemTag) ? '' : ('<' . $itemTag . '>');
        $itemCurrentBegin = empty($itemTag) ? '' : ('<' . $itemTag
            . (empty($currentClass) ? '' : ' class="' . $currentClass . '"') . '>');
        $itemPrevBegin = empty($itemTag) ? '' : ('<' . $itemTag
            . (empty($prevClass) ? '' : ' class="' . $prevClass . '"') . '>');
        $itemNextBegin = empty($itemTag) ? '' : ('<' . $itemTag
            . (empty($nextClass) ? '' : ' class="' . $nextClass . '"') . '>');
        $itemEnd = empty($itemTag) ? '' : ('</' . $itemTag . '>');
        $textBegin = empty($textTag) ? '' : ('<' . $textTag . '>');
        $textEnd = empty($textTag) ? '' : ('</' . $textTag . '>');
        $linkBegin = '<a href="%s">';
        $linkCurrentBegin = empty($itemTag) ? ('<a href="%s"'
            . (empty($currentClass) ? '' : ' class="' . $currentClass . '"') . '>')
            : $linkBegin;
        $linkPrevBegin = empty($itemTag) ? ('<a href="%s"'
            . (empty($prevClass) ? '' : ' class="' . $prevClass . '"') . '>')
            : $linkBegin;
        $linkNextBegin = empty($itemTag) ? ('<a href="%s"'
            . (empty($nextClass) ? '' : ' class="' . $nextClass . '"') . '>')
            : $linkBegin;
        $linkEnd = '</a>';

        $from = max(1, $this->currentPage - $splitPage);
        $to = min($this->totalPage, $this->currentPage + $splitPage);

        if ($this->currentPage > 1) {
            echo $itemPrevBegin . sprintf(
                $linkPrevBegin,
                str_replace($this->pageHolder, $this->currentPage - 1, $this->pageTemplate) . $this->anchor
            )
                . $prevWord . $linkEnd . $itemEnd;
        }

        if ($from > 1) {
            echo $itemBegin
                . sprintf($linkBegin, str_replace($this->pageHolder, 1, $this->pageTemplate) . $this->anchor)
                . '1' . $linkEnd . $itemEnd;

            if ($from > 2) {
                echo $itemBegin . $textBegin . $splitWord . $textEnd . $itemEnd;
            }
        }

        for ($i = $from; $i <= $to; $i++) {
            $current = ($i == $this->currentPage);

            echo ($current ? $itemCurrentBegin : $itemBegin) . sprintf(
                ($current ? $linkCurrentBegin : $linkBegin),
                str_replace($this->pageHolder, $i, $this->pageTemplate) . $this->anchor
            )
                . $i . $linkEnd . $itemEnd;
        }

        if ($to < $this->totalPage) {
            if ($to < $this->totalPage - 1) {
                echo $itemBegin . $textBegin . $splitWord . $textEnd . $itemEnd;
            }

            echo $itemBegin
                . sprintf(
                    $linkBegin,
                    str_replace($this->pageHolder, $this->totalPage, $this->pageTemplate) . $this->anchor
                )
                . $this->totalPage . $linkEnd . $itemEnd;
        }

        if ($this->currentPage < $this->totalPage) {
            echo $itemNextBegin . sprintf(
                $linkNextBegin,
                str_replace($this->pageHolder, $this->currentPage + 1, $this->pageTemplate) . $this->anchor
            )
                . $nextWord . $linkEnd . $itemEnd;
        }
    }
}
