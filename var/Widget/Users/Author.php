<?php

namespace Widget\Users;

use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Author extends Users
{
    public function execute()
    {
        if (isset($this->parameter->uid)) {
            $row = $this->db->fetchRow($this->select()->where('uid = ?', $this->parameter->uid));
            if ($row) {
                unset($row['password'], $row['authCode']);
                $this->push($row);
            }
        }
    }
}
