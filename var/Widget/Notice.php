<?php

namespace Widget;

use Typecho\Widget;
use Utils\Session;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Notice extends Widget
{
    public string $highlight = '';

    public function highlight(string $theId)
    {
        $this->highlight = $theId;
        if (Session::start()) {
            $_SESSION['__typecho_notice_highlight'] = $theId;
        }
    }

    public function getHighlightId(): int
    {
        return preg_match('/[0-9]+/', $this->highlight, $matches) ? (int) $matches[0] : 0;
    }

    public function set(string|array $value, ?string $type = 'notice', string $typeFix = 'notice')
    {
        $notice = is_array($value) ? array_values($value) : [$value];
        if (empty($type) && $typeFix) {
            $type = $typeFix;
        }

        if (Session::start()) {
            $_SESSION['__typecho_notice'] = $notice;
            $_SESSION['__typecho_notice_type'] = $type;
        }
    }
}
