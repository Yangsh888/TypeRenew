<?php

namespace Typecho\Widget\Helper\PageNavigator;

use Typecho\Widget\Helper\PageNavigator;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Classic extends PageNavigator
{
    public function render(string $prevWord = 'PREV', string $nextWord = 'NEXT')
    {
        $this->prev($prevWord);
        $this->next($nextWord);
    }

    public function prev(string $prevWord = 'PREV')
    {
        if ($this->total > 0 && $this->currentPage > 1) {
            echo '<a class="prev" href="'
                . str_replace($this->pageHolder, $this->currentPage - 1, $this->pageTemplate)
                . $this->anchor . '">'
                . $prevWord . '</a>';
        }
    }

    public function next(string $nextWord = 'NEXT')
    {
        if ($this->total > 0 && $this->currentPage < $this->totalPage) {
            echo '<a class="next" title="" href="'
                . str_replace($this->pageHolder, $this->currentPage + 1, $this->pageTemplate)
                . $this->anchor . '">'
                . $nextWord . '</a>';
        }
    }
}
