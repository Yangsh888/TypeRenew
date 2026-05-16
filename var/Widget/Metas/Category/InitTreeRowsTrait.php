<?php

namespace Widget\Metas\Category;

trait InitTreeRowsTrait
{
    protected function initTreeRows(): array
    {
        return $this->db->fetchAll($this->select()
            ->where('type = ?', 'category'));
    }
}
