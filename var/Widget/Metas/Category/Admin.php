<?php

namespace Widget\Metas\Category;

use Typecho\Common;
use Typecho\Widget\Exception;
use Widget\Base\Metas;
use Widget\Base\TreeTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Admin extends Metas
{
    use InitTreeRowsTrait;
    use TreeTrait;

    private int $parentId = 0;

    public function execute()
    {
        $this->parentId = $this->request->filter('int')->get('parent', 0);
        $this->pushAll($this->getRows($this->getChildIds($this->parentId)));
    }

    public function backLink()
    {
        if ($this->parentId) {
            $category = $this->getRow($this->parentId);

            if (!empty($category)) {
                $parent = $this->getRow($category['parent']);

                if ($parent) {
                    echo '<a href="'
                        . Common::url('manage-categories.php?parent=' . $parent['mid'], $this->options->adminUrl)
                        . '">';
                } else {
                    echo '<a href="' . Common::url('manage-categories.php', $this->options->adminUrl) . '">';
                }

                echo '&laquo; ';
                _e('返回父级分类');
                echo '</a>';
            }
        }
    }

    public function getMenuTitle(): ?string
    {
        if ($this->parentId) {
            $category = $this->getRow($this->parentId);

            if (!empty($category)) {
                return _t('管理 %s 的子分类', $category['name']);
            }
        } else {
            return null;
        }

        throw new Exception(_t('分类不存在'), 404);
    }

    public function getAddLink(): string
    {
        return 'category.php' . ($this->parentId ? '?parent=' . $this->parentId : '');
    }
}
