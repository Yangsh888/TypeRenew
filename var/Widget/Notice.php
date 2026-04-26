<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 提示框组件
 *
 * @package Widget
 */
class Notice extends Widget
{
    /**
     * 高亮相关元素
     *
     * @param string $theId 需要高亮元素的id
     */
    public function highlight(string $theId)
    {
        Cookie::set(
            '__typecho_notice_highlight',
            $theId
        );
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['__typecho_notice_highlight'] = $theId;
        }
    }

    /** @param string|array $value */
    public function set($value, ?string $type = 'notice', string $typeFix = 'notice')
    {
        $notice = is_array($value) ? array_values($value) : [$value];
        if (empty($type) && $typeFix) {
            $type = $typeFix;
        }

        Cookie::set(
            '__typecho_notice',
            Common::jsonEncode($notice, 0, '[]')
        );
        Cookie::set(
            '__typecho_notice_type',
            $type
        );
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['__typecho_notice'] = $notice;
            $_SESSION['__typecho_notice_type'] = $type;
        }
    }
}
