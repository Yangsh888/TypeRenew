<?php

namespace Widget\Base;

use Typecho\Common;
use Typecho\Db\Exception;
use Typecho\Db\Query;
use Typecho\Widget\Helper\Form;
use Widget\Base;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 全局选项组件
 *
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Options extends Base implements QueryInterface
{
    /**
     * 获取原始查询对象
     *
     * @param mixed ...$fields
     * @return Query
     * @throws Exception
     */
    public function select(...$fields): Query
    {
        return $this->db->select(...$fields)->from('table.options');
    }

    public function insert(array $rows): int
    {
        return $this->db->query($this->db->insert('table.options')->rows($rows));
    }

    public function update(array $rows, Query $condition): int
    {
        return $this->db->query($condition->update('table.options')->rows($rows));
    }

    public function delete(Query $condition): int
    {
        return $this->db->query($condition->delete('table.options'));
    }

    public function size(Query $condition): int
    {
        return $this->db->fetchObject($condition->select(['COUNT(name)' => 'num'])->from('table.options'))->num;
    }

    protected function validateFormOrGoBack(Form $form): void
    {
        if ($form->validate()) {
            $this->response->goBack();
        }
    }

    protected function saveOption(string $name, $value, int $user = 0): void
    {
        $this->persistOptions([$name => $value], $user);
    }

    protected function persistOptions(array $settings, int $user = 0): void
    {
        $rows = $this->normalizeOptionRows($settings, $user);

        if (empty($rows)) {
            return;
        }

        $persist = function () use ($rows, $user): void {
            $existing = $this->fetchExistingOptionNames(array_keys($rows), $user);
            $insertRows = [];
            $updateRows = [];

            foreach ($rows as $row) {
                if (isset($existing[$row['name']])) {
                    $updateRows[$row['name']] = $row['value'];
                } else {
                    $insertRows[] = $row;
                }
            }

            if (!empty($insertRows)) {
                $this->insertOptionRows($insertRows);
            }

            if (!empty($updateRows)) {
                $this->updateOptionRows($updateRows, $user);
            }
        };

        if (count($rows) > 1) {
            $this->runInTransaction($persist);
            return;
        }

        $persist();
    }

    private function normalizeOptionRows(array $settings, int $user): array
    {
        $rows = [];

        foreach ($settings as $name => $value) {
            $rows[(string) $name] = [
                'name' => (string) $name,
                'user' => $user,
                'value' => $this->normalizeOptionValue($value)
            ];
        }

        return $rows;
    }

    private function normalizeOptionValue($value): string
    {
        return is_array($value) ? Common::jsonEncode($value, 0, '{}') : (string) $value;
    }

    private function fetchExistingOptionNames(array $names, int $user): array
    {
        if (empty($names)) {
            return [];
        }

        $rows = $this->db->fetchAll(
            $this->db->select('name')
                ->from('table.options')
                ->where('user = ?', $user)
                ->where('name IN ?', $names)
        );

        return array_flip(array_column($rows, 'name'));
    }

    private function insertOptionRows(array $rows): void
    {
        $adapter = $this->db->getAdapter();
        $table = $this->db->getPrefix() . 'options';
        $nameColumn = $adapter->quoteColumn('name');
        $userColumn = $adapter->quoteColumn('user');
        $valueColumn = $adapter->quoteColumn('value');

        $sql = "INSERT INTO {$table} ({$nameColumn}, {$userColumn}, {$valueColumn}) VALUES "
            . $this->buildInsertValuesSql($rows);
        $this->db->query($sql);
    }

    private function updateOptionRows(array $rows, int $user): void
    {
        $adapter = $this->db->getAdapter();
        $table = $this->db->getPrefix() . 'options';
        $nameColumn = $adapter->quoteColumn('name');
        $userColumn = $adapter->quoteColumn('user');
        $valueColumn = $adapter->quoteColumn('value');
        $quotedUser = $adapter->quoteValue((string) $user);
        $cases = [];
        $names = [];

        foreach ($rows as $name => $value) {
            $quotedName = $adapter->quoteValue($name);
            $cases[] = "WHEN {$quotedName} THEN " . $adapter->quoteValue($value);
            $names[] = $quotedName;
        }

        $sql = "UPDATE {$table} SET {$valueColumn} = CASE {$nameColumn} "
            . implode(' ', $cases)
            . " ELSE {$valueColumn} END WHERE {$userColumn} = {$quotedUser} "
            . "AND {$nameColumn} IN (" . implode(', ', $names) . ")";
        $this->db->query($sql);
    }

    private function buildInsertValuesSql(array $rows): string
    {
        $adapter = $this->db->getAdapter();
        $values = [];

        foreach ($rows as $row) {
            $values[] = '('
                . $adapter->quoteValue($row['name']) . ', '
                . $adapter->quoteValue((string) $row['user']) . ', '
                . $adapter->quoteValue($row['value'])
                . ')';
        }

        return implode(', ', $values);
    }

    private function runInTransaction(callable $callback): void
    {
        $this->db->query('BEGIN');

        try {
            $callback();
            $this->db->query('COMMIT');
        } catch (\Throwable $throwable) {
            $this->db->query('ROLLBACK');
            throw $throwable;
        }
    }

    protected function saveSuccessAndGoBack(?string $message = null): void
    {
        Notice::alloc()->set($message ?? _t('设置已经保存'), 'success');
        $this->response->goBack();
    }

    protected function noticeAndGoBack(string $message, string $type = 'notice'): void
    {
        Notice::alloc()->set($message, $type);
        $this->response->goBack();
    }
}
