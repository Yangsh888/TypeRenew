<?php

namespace Typecho\Widget\Helper;

/**
 * widget对象帮手,用于处理空对象方法
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class EmptyClass
{
    public function __call(string $name, array $args)
    {
        return $this;
    }
}
