<?php

namespace Widget\Contents\Attachment;

use Typecho\Config;
use Typecho\Db;
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

        if ($this->request->is('parent')) {
            $select->where('table.contents.parent = ?', $this->request->filter('int')->get('parent'));
        }

        $this->searchQuery($select);

        if ($this->request->is('mime')) {
            $mime = (string) $this->request->get('mime');
            $pageSize = max(1, (int) $this->parameter->pageSize);
            $offset = $this->request->is('offset')
                ? max(0, $this->request->filter('int')->get('offset'))
                : (max(1, $this->currentPage) - 1) * $pageSize;
            $select->order('table.contents.created', Db::SORT_DESC)->order('table.contents.cid', Db::SORT_DESC);
            $matching = [];
            $this->total = 0;
            for ($start = 0; ; $start += 200) {
                $rows = $this->db->fetchAll((clone $select)->limit(200)->offset($start));
                foreach ($rows as $row) {
                    $attachment = json_decode($row['text'], true);
                    if (is_array($attachment) && ($attachment['mime'] ?? '') === $mime) {
                        if ($this->total >= $offset && count($matching) < $pageSize) {
                            $matching[] = $row;
                        }
                        $this->total++;
                    }
                }
                if (count($rows) < 200) {
                    break;
                }
            }
            foreach ($matching as $row) {
                $this->push($row);
            }
            return;
        }

        $this->countTotal($select);

        $select->order('table.contents.created', Db::SORT_DESC);
        if ($this->request->is('offset')) {
            $select->limit($this->parameter->pageSize)->offset(max(0, $this->request->filter('int')->get('offset')));
        } else {
            $select->page($this->currentPage, $this->parameter->pageSize);
        }

        $this->db->fetchAll($select, [$this, 'push']);
    }

    protected function ___parentPost(): Config
    {
        return new Config($this->db->fetchRow(
            $this->select()->where('table.contents.cid = ?', $this->parent)->limit(1)
        ));
    }
}
