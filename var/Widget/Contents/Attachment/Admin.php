<?php

namespace Widget\Contents\Attachment;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Exception;
use Widget\Base\Contents;
use Widget\Contents\AdminTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Admin extends Contents
{
    use AdminTrait;

    public function execute()
    {
        $this->initPage();

        $select = $this->select()->where('table.contents.type = ?', 'attachment');

        if (!$this->user->pass('editor', true)) {
            $select->where('table.contents.authorId = ?', $this->user->uid);
        }

        $this->searchQuery($select);
        $this->countTotal($select);

        $select->order('table.contents.created', Db::SORT_DESC)
            ->page($this->currentPage, $this->parameter->pageSize);

        $this->db->fetchAll($select, [$this, 'push']);
    }

    /**
     * 所属文章
     * @throws Exception
     */
    protected function ___parentPost(): Config
    {
        return new Config($this->db->fetchRow(
            $this->select()->where('table.contents.cid = ?', $this->parent)->limit(1)
        ));
    }

    protected function ___parentEditor(): string
    {
        $target = $this->resolveParentEditorTarget();
        return ($target['type'] ?? 'post') === 'page' ? 'page' : 'post';
    }

    protected function ___parentEditorCid(): int
    {
        $target = $this->resolveParentEditorTarget();
        return (int) ($target['cid'] ?? 0);
    }

    private function resolveParentEditorTarget(): array
    {
        $parent = $this->parentPost;
        $type = (string) ($parent->type ?? '');
        $cid = (int) ($parent->cid ?? 0);
        $resolvedType = in_array($type, ['page', 'page_draft'], true) ? 'page' : 'post';

        if ($type === 'revision') {
            $rootCid = (int) ($parent->parent ?? 0);
            if ($rootCid > 0) {
                $root = $this->db->fetchRow(
                    $this->select('type')
                        ->where('table.contents.cid = ?', $rootCid)
                        ->limit(1)
                );

                $rootType = (string) ($root['type'] ?? '');
                $resolvedType = in_array($rootType, ['page', 'page_draft'], true) ? 'page' : 'post';
                $cid = $rootCid;
            }
        }

        return ['type' => $resolvedType, 'cid' => $cid];
    }
}
