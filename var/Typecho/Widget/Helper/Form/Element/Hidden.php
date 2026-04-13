<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 隐藏域帮手类
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Hidden extends Element
{
    use TextInputTrait;

    /**
     * 初始化时隐藏容器
     *
     * @return void
     */
    public function init()
    {
        $this->setAttribute('style', 'display:none');
    }

    /**
     * @param string $value
     * @return string
     */
    protected function filterValue(string $value): string
    {
        return htmlspecialchars($value);
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        return 'hidden';
    }
}
