<?php

namespace Widget\Users;

use Typecho\Db\Exception;

/**
 * 编辑用户组件
 */
trait EditTrait
{
    /**
     * 判断用户名称是否可用
     *
     * @param string $name 用户名称
     * @return boolean
     * @throws Exception
     */
    public function nameExists(string $name): bool
    {
        return $this->isFieldAvailable('name', $name);
    }

    /**
     * 判断电子邮件是否可用
     *
     * @param string $mail 电子邮件
     * @return boolean
     * @throws Exception
     */
    public function mailExists(string $mail): bool
    {
        return $this->isFieldAvailable('mail', $mail);
    }

    /**
     * 判断用户昵称是否可用
     *
     * @param string $screenName 昵称
     * @return boolean
     * @throws Exception
     */
    public function screenNameExists(string $screenName): bool
    {
        return $this->isFieldAvailable('screenName', $screenName);
    }

    /**
     * 判断字段值是否可用
     *
     * @param string $field 字段名
     * @param string $value 字段值
     * @return bool
     * @throws Exception
     */
    private function isFieldAvailable(string $field, string $value): bool
    {
        $select = $this->db->select()
            ->from('table.users')
            ->where("{$field} = ?", $value)
            ->limit(1);

        if ($this->request->is('uid')) {
            $select->where('uid <> ?', $this->request->get('uid'));
        }

        $user = $this->db->fetchRow($select);
        return !$user;
    }

    /**
     * @param string $column 字段名
     * @param int $offset 偏移值
     * @param string|null $group 用户组
     * @param int $pageSize 分页值
     * @return int
     * @throws Exception
     */
    protected function getPageOffset(string $column, int $offset, ?string $group = null, int $pageSize = 20): int
    {
        $select = $this->db->select(['COUNT(uid)' => 'num'])->from('table.users')
            ->where("table.users.{$column} > {$offset}");

        if (!empty($group)) {
            $select->where('table.users.group = ?', $group);
        }

        $count = $this->db->fetchObject($select)->num + 1;
        return ceil($count / $pageSize);
    }
}
