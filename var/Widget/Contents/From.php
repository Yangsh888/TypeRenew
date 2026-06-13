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
        // 预取行直接入栈, 不再查库 (供批量预热使用, 消除评论列表按 cid 逐行查父内容的 N+1)
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

    /**
     * 批量预热 From 池: 一次查回多个 cid 的内容行, 按 cid 注入对应的 From@cid 池实例。
     * 之后对这些 cid 调用 From::allocWithAlias($cid, ...) 将命中暖池, 不再逐行查库。
     *
     * @param int[] $cids
     */
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
            // 预热失败回退到逐行惰性加载, 不影响正确性
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
