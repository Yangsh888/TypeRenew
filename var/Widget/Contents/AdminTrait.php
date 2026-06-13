<?php

namespace Widget\Contents;

use Typecho\Config;
use Typecho\Db\Query;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\PageNavigator\Box;

trait AdminTrait
{
    private ?int $total;

    private int $currentPage;

    protected function initPage()
    {
        $this->parameter->setDefault('pageSize=20');
        $this->currentPage = $this->request->filter('int')->get('page', 1);
    }

    protected function searchQuery(Query $select)
    {
        if ($this->request->is('keywords')) {
            $keywords = $this->request->filter('search')->get('keywords');
            $args = [];
            $keywordsList = explode(' ', $keywords);
            $args[] = implode(' OR ', array_fill(0, count($keywordsList), 'table.contents.title LIKE ?'));

            foreach ($keywordsList as $keyword) {
                $args[] = '%' . $keyword . '%';
            }

            $select->where(...$args);
        }
    }

    protected function countTotal(Query $select)
    {
        $countSql = clone $select;
        $this->total = $this->size($countSql);
    }

    public function pageNav()
    {
        $query = $this->request->makeUriByRequest('page={page}');

        $nav = new Box(
            $this->total,
            $this->currentPage,
            $this->parameter->pageSize,
            $query
        );

        $nav->render('&laquo;', '&raquo;');
    }

    protected function ___revision(): ?array
    {
        return $this->db->fetchRow(
            $this->select('cid', 'modified')
                ->where(
                    'table.contents.parent = ? AND table.contents.type = ?',
                    $this->cid,
                    'revision'
                )
                ->limit(1)
        );
    }
}
