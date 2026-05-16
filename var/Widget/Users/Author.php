<?php

namespace Widget\Users;

use Typecho\Db\Exception;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Author extends Users
{
    public function execute()
    {
        if (isset($this->parameter->uid)) {
            $this->db->fetchRow($this->select()
                ->where('uid = ?', $this->parameter->uid), [$this, 'push']);
        }
    }
}
