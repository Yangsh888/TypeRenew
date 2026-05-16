<?php

namespace Widget\Contents\Page;

use Typecho\Config;
use Widget\Base\Contents;
use Widget\Base\TreeViewTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Rows extends Contents
{
    use TreeViewTrait;

    public function execute()
    {
        $this->pushAll($this->getRows($this->orders, $this->parameter->ignore));
    }

    protected function initTreeRows(): array
    {
        $select = $this->select(
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
        )->where('table.contents.type = ?', 'page')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $this->options->time);

        $frontPage = explode(':', $this->options->frontPage);
        if (2 == count($frontPage) && 'page' == $frontPage[0]) {
            $select->where('table.contents.cid <> ?', $frontPage[1]);
        }

        return $this->db->fetchAll($select);
    }

    public function listPages($pageOptions = null)
    {
        $pageOptions = Config::factory($pageOptions);
        $pageOptions->setDefault([
            'wrapTag'       => 'ul',
            'wrapClass'     => '',
            'itemTag'       => 'li',
            'itemClass'     => '',
            'showCount'     => false,
            'showFeed'      => false,
            'countTemplate' => '(%d)',
            'feedTemplate'  => '<a href="%s">RSS</a>'
        ]);

        self::pluginHandle()->trigger($plugged)->call('listPages', $pageOptions, $this);

        if (!$plugged) {
            $this->listRows($pageOptions, 'treeViewPagesCallback', intval($this->parameter->current));
        }
    }
}
