<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Notice extends Widget
{
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

    public function set(string|array $value, ?string $type = 'notice', string $typeFix = 'notice')
    {
        $notice = is_array($value) ? array_values($value) : [$value];
        if (empty($type) && $typeFix) {
            $type = $typeFix;
        }

        $payload = Common::jsonEncode($notice, 0, '[]');

        Cookie::set(
            '__typecho_notice',
            $payload
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
