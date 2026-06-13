<?php

namespace Widget\Contents;

use Typecho\Config;
use Widget\Base\Contents;
use Widget\Base\TreeTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class From extends Contents
{
    use TreeTrait {
        initParameter as initTreeParameter;
        ___directory as ___treeDirectory;
    }

    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault([
            'cid' => null,
            'query' => null,
            'rows' => null,
        ]);
    }

    public function execute()
    {
        if (is_array($this->parameter->rows)) {
            $this->pushAll($this->parameter->rows);

            if ($this->type == 'page') {
                $this->initTreeParameter($this->parameter);
            }
            return;
        }

        $query = null;

        if (isset($this->parameter->cid)) {
            $query = $this->select()->where('cid = ?', $this->parameter->cid);
        } elseif (isset($this->parameter->query)) {
            $query = $this->parameter->query;
        }

        if ($query) {
            $this->db->fetchAll($query, [$this, 'push']);

            if ($this->type == 'page') {
                $this->initTreeParameter($this->parameter);
            }
        }
    }

    public static function preload(array $cids): void
    {
        $cids = array_values(array_unique(array_filter(array_map('intval', $cids))));
        if (count($cids) < 2) {
            return;
        }

        try {
            $db = \Typecho\Db::get();
            $rows = $db->fetchAll($db->select()->from('table.contents')->where('cid IN ?', $cids));

            $byCid = [];
            foreach ($rows as $row) {
                $byCid[(int) $row['cid']][] = $row;
            }

            foreach ($byCid as $cid => $cidRows) {
                self::allocWithAlias((string) $cid, ['rows' => $cidRows]);
            }
        } catch (\Throwable $e) {
        }
    }

    protected function ___directory(): array
    {
        return $this->type == 'page' ? $this->___treeDirectory() : parent::___directory();
    }

    protected function initTreeRows(): array
    {
        return $this->db->fetchAll($this->select(
            'table.contents.cid',
            'table.contents.title',
            'table.contents.slug',
            'table.contents.created',
            'table.contents.authorId',
            'table.contents.modified',
            'table.contents.type',
            'table.contents.status',
            'table.contents.commentsNum',
            'table.contents.order',
            'table.contents.parent',
            'table.contents.template',
            'table.contents.password',
            'table.contents.allowComment',
            'table.contents.allowPing',
            'table.contents.allowFeed'
        )->where('table.contents.type = ?', 'page'));
    }
}
