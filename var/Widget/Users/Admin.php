<?php

namespace Widget\Users;

use Typecho\Common;
use Typecho\Db;
use Typecho\Db\Query;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\PageNavigator\Box;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Admin extends Users
{
    private Query $countSql;
    private int $total;
    private int $currentPage;

    /**
     * @throws Db\Exception
     */
    public function execute()
    {
        $this->parameter->setDefault('pageSize=20');
        $select = $this->select();
        $this->currentPage = $this->request->filter('int')->get('page', 1);

        if (null != ($keywords = $this->request->get('keywords'))) {
            $keywords = '%' . Common::filterSearchQuery($keywords) . '%';
            $select->where(
                'name LIKE ? OR screenName LIKE ? OR mail LIKE ?',
                $keywords,
                $keywords,
                $keywords
            );
        }

        $this->countSql = clone $select;

        $select->order('table.users.uid')
            ->page($this->currentPage, $this->parameter->pageSize);

        $this->db->fetchAll($select, [$this, 'push']);
        $this->hydratePostsCount();
    }

    /**
     * 输出分页
     *
     * @throws Exception|Db\Exception
     */
    public function pageNav()
    {
        $query = $this->request->makeUriByRequest('page={page}');

        $nav = new Box(
            !isset($this->total) ? $this->total = $this->size($this->countSql) : $this->total,
            $this->currentPage,
            $this->parameter->pageSize,
            $query
        );
        $nav->render('&laquo;', '&raquo;');
    }

    private function hydratePostsCount(): void
    {
        if ($this->stack === []) {
            return;
        }

        $uids = array_values(array_filter(array_map(
            static fn(array $row): int => (int) ($row['uid'] ?? 0),
            $this->stack
        )));

        if ($uids === []) {
            return;
        }

        $rows = $this->db->fetchAll(
            $this->db->select('table.contents.authorId', ['COUNT(table.contents.cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.authorId IN ?', $uids)
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->group('table.contents.authorId')
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) ($row['authorId'] ?? 0)] = (int) ($row['num'] ?? 0);
        }

        foreach ($this->stack as &$row) {
            $uid = (int) ($row['uid'] ?? 0);
            $row['postsNum'] = $counts[$uid] ?? 0;
        }
        unset($row);
    }
}
