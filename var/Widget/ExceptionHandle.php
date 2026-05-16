<?php

namespace Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class ExceptionHandle extends Base
{
    public function execute()
    {
        Archive::allocWithAlias('404', 'type=404')->render();
    }
}
