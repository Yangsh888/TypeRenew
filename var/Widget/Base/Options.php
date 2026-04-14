<?php

namespace Widget\Base;

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
        $exists = $this->db->fetchRow(
            $this->db->select('name')
                ->from('table.options')
                ->where('name = ?', $name)
                ->where('user = ?', $user)
        );
        $value = is_array($value) ? json_encode($value) : (string) $value;

        if ($exists) {
            $this->update(
                ['value' => $value],
                $this->db->sql()->where('name = ?', $name)->where('user = ?', $user)
            );
            return;
        }

        $this->insert([
            'name' => $name,
            'user' => $user,
            'value' => $value
        ]);
    }

    protected function persistOptions(array $settings, int $user = 0): void
    {
        foreach ($settings as $name => $value) {
            $this->saveOption($name, $value, $user);
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
