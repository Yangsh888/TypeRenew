<?php

namespace Widget\Metas\Tag;

use Typecho\Common;
use Typecho\Db;
use Widget\Base\Metas;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Cloud extends Metas
{
    public function execute()
    {
        $this->parameter->setDefault(['sort' => 'count', 'ignoreZeroCount' => false, 'desc' => true, 'limit' => 0]);
        $select = $this->select()->where('type = ?', 'tag')
            ->order($this->parameter->sort, $this->parameter->desc ? Db::SORT_DESC : Db::SORT_ASC);

        if ($this->parameter->ignoreZeroCount) {
            $select->where('count > 0');
        }

        if ($this->parameter->limit) {
            $select->limit($this->parameter->limit);
        }

        $this->db->fetchAll($select, [$this, 'push']);
    }

    /**
     * 按分割数输出字符串
     *
     * @param mixed ...$args 需要输出的值
     */
    public function split(...$args)
    {
        array_unshift($args, $this->count);
        echo call_user_func_array([Common::class, 'splitByCount'], $args);
    }
}
