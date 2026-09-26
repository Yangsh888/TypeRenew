<?php

namespace Widget\Comments;

use Typecho\Config;
use Typecho\Db;
use Widget\Base\Comments;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Recent extends Comments
{
    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault(
            ['pageSize' => $this->options->commentsListSize, 'parentId' => 0, 'ignoreAuthor' => false]
        );
    }

    public function execute()
    {
        $select = $this->select('table.comments.*')->limit($this->parameter->pageSize)
            ->join('table.contents', 'table.contents.cid = table.comments.cid')
            ->where('table.comments.status = ?', 'approved')
            ->where('table.contents.status = ?', 'publish')
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->order('table.comments.coid', Db::SORT_DESC);

        if ($this->parameter->parentId) {
            $select->where('table.comments.cid = ?', $this->parameter->parentId);
        }

        if ($this->options->commentsShowCommentOnly) {
            $select->where('table.comments.type = ?', 'comment');
        }

        if ($this->parameter->ignoreAuthor) {
            $select->where('table.comments.ownerId <> table.comments.authorId');
        }

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
