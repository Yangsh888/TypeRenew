<?php

namespace Widget\Metas\Category;

use Typecho\Config;
use Widget\Base\Metas;
use Widget\Base\TreeViewTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * @property-read int $levels
 * @property-read array $children
 */
class Rows extends Metas
{
    use InitTreeRowsTrait;
    use TreeViewTrait;

    public function execute()
    {
        $this->pushAll($this->getRows($this->orders, $this->parameter->ignore));
    }

    public function listCategories($categoryOptions = null)
    {
        $categoryOptions = Config::factory($categoryOptions);
        $categoryOptions->setDefault([
            'wrapTag'       => 'ul',
            'wrapClass'     => '',
            'itemTag'       => 'li',
            'itemClass'     => '',
            'showCount'     => false,
            'showFeed'      => false,
            'countTemplate' => '(%d)',
            'feedTemplate'  => '<a href="%s">RSS</a>'
        ]);

        // 插件接口
        self::pluginHandle()->trigger($plugged)->call('listCategories', $categoryOptions, $this);

        if (!$plugged) {
            $this->listRows(
                $categoryOptions,
                'category',
                'treeViewCategoriesCallback',
                intval($this->parameter->current)
            );
        }
    }
}
