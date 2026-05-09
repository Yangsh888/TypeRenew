<?php

namespace Widget\Comments;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Exception;
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

    /**
     * @throws Exception
     */
    public function execute()
    {
        $select = $this->select()->limit($this->parameter->pageSize)
            ->where('table.comments.status = ?', 'approved')
            ->order('table.comments.coid', Db::SORT_DESC);

        if ($this->parameter->parentId) {
            $select->where('cid = ?', $this->parameter->parentId);
        }

        if ($this->options->commentsShowCommentOnly) {
            $select->where('type = ?', 'comment');
        }

        if ($this->parameter->ignoreAuthor) {
            $select->where('ownerId <> authorId');
        }

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
