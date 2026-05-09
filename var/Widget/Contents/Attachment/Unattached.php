<?php

namespace Widget\Contents\Attachment;

use Typecho\Db;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Unattached extends Contents
{
    public function execute()
    {
        $select = $this->select()->where('table.contents.type = ? AND
        (table.contents.parent = 0 OR table.contents.parent IS NULL)', 'attachment');

        $select->where('table.contents.authorId = ?', $this->user->uid);

        $select->order('table.contents.created', Db::SORT_DESC);

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
