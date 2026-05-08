<?php

namespace Widget\Metas\Category;

use Typecho\Db\Exception;

trait InitTreeRowsTrait
{
    protected function initTreeRows(): array
    {
        return $this->db->fetchAll($this->select()
            ->where('type = ?', 'category'));
    }
}
