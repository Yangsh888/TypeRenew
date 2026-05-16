<?php

namespace Typecho\Db\Adapter;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use Typecho\Db\Exception as DbException;

class SQLException extends DbException
{
}
