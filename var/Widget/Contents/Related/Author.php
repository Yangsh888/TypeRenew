<?php

namespace Widget\Contents\Related;

use Typecho\Db;
use Typecho\Db\Exception;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Author extends Contents
{
    public function execute()
    {
        $this->parameter->setDefault('limit=5');

        if ($this->parameter->author) {
            $this->db->fetchAll($this->select()
                ->where('table.contents.authorId = ?', $this->parameter->author)
                ->where('table.contents.cid <> ?', $this->parameter->cid)
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.password IS NULL')
                ->where('table.contents.created < ?', $this->options->time)
                ->where('table.contents.type = ?', $this->parameter->type)
                ->order('table.contents.created', Db::SORT_DESC)
                ->limit($this->parameter->limit), [$this, 'push']);
        }
    }
}
