<?php

namespace Widget\Contents\Attachment;

use Typecho\Db;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Related extends Contents
{
    public function execute()
    {
        $this->parameter->setDefault('parentId=0&parentIds=&limit=0');

        $parentIds = [];
        
        if (!empty($this->parameter->parentIds)) {
            if (is_array($this->parameter->parentIds)) {
                $parentIds = array_filter(array_map('intval', $this->parameter->parentIds));
            } elseif (is_string($this->parameter->parentIds)) {
                $parentIds = array_filter(array_map('intval', explode(',', $this->parameter->parentIds)));
            }
        }
        
        if ($this->parameter->parentId > 0 && !in_array((int)$this->parameter->parentId, $parentIds)) {
            $parentIds[] = (int)$this->parameter->parentId;
        }

        if (empty($parentIds)) {
            return;
        }

        $select = $this->select()->where('table.contents.type = ?', 'attachment');
        
        if (count($parentIds) === 1) {
            $select->where('table.contents.parent = ?', $parentIds[0]);
        } else {
            $select->where('table.contents.parent IN ?', $parentIds);
        }

        $select->order('table.contents.created');

        if ($this->parameter->limit > 0) {
            $select->limit($this->parameter->limit);
        }

        if ($this->parameter->offset > 0) {
            $select->offset($this->parameter->offset);
        }

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
