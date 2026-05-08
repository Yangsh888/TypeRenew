<?php

namespace Widget\Metas\Tag;

use Typecho\Db;
use Typecho\Widget\Exception;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Admin extends Cloud
{
    public function execute()
    {
        $select = $this->select()->where('type = ?', 'tag')->order('mid', Db::SORT_DESC);
        $this->db->fetchAll($select, [$this, 'push']);
    }

    public function getMenuTitle(): ?string
    {
        if ($this->request->is('mid')) {
            $tag = $this->db->fetchRow($this->select()
                ->where('type = ? AND mid = ?', 'tag', $this->request->get('mid')));

            if (!empty($tag)) {
                return _t('编辑标签 %s', $tag['name']);
            }
        }

        if (!$this->request->is('mid')) {
            return null;
        }

        throw new Exception(_t('标签不存在'), 404);
    }
}
