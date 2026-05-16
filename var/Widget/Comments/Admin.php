<?php

namespace Widget\Comments;

use Typecho\Cookie;
use Typecho\Db;
use Typecho\Db\Query;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\PageNavigator\Box;
use Widget\Base\Comments;
use Widget\Base\Contents;
use Widget\Contents\From;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Admin extends Comments
{
    private Query $countSql;

    private int $currentPage;

    private ?int $total;

    public function getMenuTitle(): string
    {
        $content = $this->parentContent;

        if ($content) {
            return _t('%s的评论', $content->title);
        }

        throw new Exception(_t('内容不存在'), 404);
    }

    public function execute()
    {
        $select = $this->select();
        $this->parameter->setDefault('pageSize=20');
        $this->currentPage = $this->request->filter('int')->get('page', 1);

        if (null != ($keywords = $this->request->filter('search')->get('keywords'))) {
            $select->where('table.comments.text LIKE ?', '%' . $keywords . '%');
        }

        if (!$this->user->pass('editor', true)) {
            $select->where('table.comments.ownerId = ?', $this->user->uid);
        } elseif (!$this->request->is('cid')) {
            if ($this->request->is('__typecho_all_comments=on')) {
                Cookie::set('__typecho_all_comments', 'on');
            } else {
                if ($this->request->is('__typecho_all_comments=off')) {
                    Cookie::set('__typecho_all_comments', 'off');
                }

                if ('on' != Cookie::get('__typecho_all_comments')) {
                    $select->where('table.comments.ownerId = ?', $this->user->uid);
                }
            }
        }

        if (in_array($this->request->get('status'), ['approved', 'waiting', 'spam'])) {
            $select->where('table.comments.status = ?', $this->request->get('status'));
        } elseif ('hold' == $this->request->get('status')) {
            $select->where('table.comments.status <> ?', 'approved');
        } else {
            $select->where('table.comments.status = ?', 'approved');
        }

        if ($this->request->is('cid')) {
            $select->where('table.comments.cid = ?', $this->request->filter('int')->get('cid'));
        }

        $this->countSql = clone $select;

        $select->order('table.comments.coid', Db::SORT_DESC)
            ->page($this->currentPage, $this->parameter->pageSize);

        $this->db->fetchAll($select, [$this, 'push']);
    }

    public function pageNav()
    {
        $query = $this->request->makeUriByRequest('page={page}');

        $nav = new Box(
            !isset($this->total) ? $this->total = $this->size($this->countSql) : $this->total,
            $this->currentPage,
            $this->parameter->pageSize,
            $query
        );
        $nav->render(_t('&laquo;'), _t('&raquo;'));
    }

    protected function ___parentContent(): Contents
    {
        $cid = $this->request->is('cid') ? $this->request->filter('int')->get('cid') : $this->cid;
        return From::allocWithAlias($cid, ['cid' => $cid]);
    }

    protected function ___permalink(): string
    {
        if ('approved' === $this->status) {
            return parent::___permalink();
        }

        return '#' . $this->theId;
    }
}
