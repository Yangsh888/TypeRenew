<?php

namespace Widget\Users;

use Typecho\Common;
use Typecho\Db;
use Typecho\Db\Query;
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

    public function execute()
    {
        $this->parameter->setDefault('pageSize=20');
        $select = $this->select();
        $this->currentPage = $this->request->filter('int')->get('page', 1);

        if (null != ($keywords = $this->request->get('keywords'))) {
            $op = $this->db->getAdapter()->getDriver() == 'pgsql' ? 'ILIKE' : 'LIKE';
            $filteredKeywords = Common::filterSearchQuery($keywords);
            $mailKeywords = trim((string) $keywords);
            $conditions = [];
            $values = [];

            if ($filteredKeywords !== '') {
                $conditions[] = "name {$op} ? OR screenName {$op} ?";
                $values[] = '%' . $filteredKeywords . '%';
                $values[] = '%' . $filteredKeywords . '%';
            }

            if ($mailKeywords !== '') {
                $conditions[] = "mail {$op} ?";
                $values[] = '%' . $mailKeywords . '%';
            }

            if ($conditions !== []) {
                $select->where(implode(' OR ', $conditions), ...$values);
            }
        }

        $this->countSql = clone $select;

        $select->order('table.users.uid')
            ->page($this->currentPage, $this->parameter->pageSize);

        $rows = $this->db->fetchAll($select);
        if (empty($rows)) {
            return;
        }

        $uids = array_map('intval', array_column($rows, 'uid'));
        $counts = [];

        if ($uids !== []) {
            $result = $this->db->fetchAll($this->db->select('authorId', ['COUNT(cid)' => 'num'])
                ->from('table.contents')
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.authorId IN ?', $uids)
                ->group('table.contents.authorId'));

            foreach ($result as $row) {
                $counts[(int) $row['authorId']] = (int) $row['num'];
            }
        }

        foreach ($rows as $row) {
            $row['postsNum'] = $counts[(int) $row['uid']] ?? 0;
            $this->push($row);
        }
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
        $nav->render('&laquo;', '&raquo;');
    }

    /**
     * 仅仅输出域名和路径
     */
    protected function ___domainPath(): string
    {
        $parts = \Typecho\Common::parseUrl((string) $this->url);
        return (string) ($parts['host'] ?? '') . (string) ($parts['path'] ?? '');
    }

    /**
     * 发布文章数
     */
    protected function ___postsNum(): int
    {
        if (isset($this->row['postsNum'])) {
            return (int) $this->row['postsNum'];
        }

        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.authorId = ?', $this->uid))->num;
    }

}
