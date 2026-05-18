<?php

namespace Widget\Users;

trait EditTrait
{
    public function nameExists(string $name): bool
    {
        return $this->isFieldAvailable('name', $name);
    }

    public function mailExists(string $mail): bool
    {
        return $this->isFieldAvailable('mail', $mail);
    }

    public function screenNameExists(string $screenName): bool
    {
        return $this->isFieldAvailable('screenName', $screenName);
    }

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

    protected function getPageOffset(string $column, int $offset, ?string $group = null, int $pageSize = 20): int
    {
        $select = $this->db->select(['COUNT(uid)' => 'num'])->from('table.users')
            ->where("table.users.{$column} > {$offset}");

        if (!empty($group)) {
            $select->where('table.users.group = ?', $group);
        }

        $row = $this->db->fetchObject($select);
        $count = (int) ($row->num ?? 0) + 1;
        return ceil($count / $pageSize);
    }

    protected function userWriteConflict(\Throwable $e): ?string
    {
        $code = (string) $e->getCode();
        $message = strtolower($e->getMessage());

        if (
            $code !== '1062'
            && $code !== '23000'
            && $code !== '23505'
            && !str_contains($message, 'duplicate')
            && !str_contains($message, 'unique constraint')
            && !str_contains($message, '1062')
            && !str_contains($message, '23000')
            && !str_contains($message, '23505')
        ) {
            return null;
        }

        if (str_contains($message, 'screenname')) {
            return _t('昵称已经存在');
        }

        if (str_contains($message, 'mail')) {
            return _t('电子邮箱地址已经存在');
        }

        return _t('用户名已经存在');
    }
}
