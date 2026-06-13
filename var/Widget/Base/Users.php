<?php

namespace Widget\Base;

use Typecho\Common;
use Typecho\Config;
use Typecho\Db\Query;
use Typecho\Router;
use Typecho\Router\ParamsDelegateInterface;
use Utils\Comment;
use Widget\Base;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Users extends Base implements QueryInterface, RowFilterInterface, PrimaryKeyInterface, ParamsDelegateInterface
{
    public function getPrimaryKey(): string
    {
        return 'uid';
    }

    public function push(array $value): array
    {
        $value = $this->filter($value);
        return parent::push($value);
    }

    public function filter(array $row): array
    {
        return Users::pluginHandle()->filter('filter', $row, $this);
    }

    public function getRouterParam(string $key): string
    {
        return $key === 'uid' ? (string) $this->uid : '{' . $key . '}';
    }

    public function select(...$fields): Query
    {
        return $this->db->select(...$fields)->from('table.users');
    }

    public function size(Query $condition): int
    {
        return $this->db->fetchObject($condition->select(['COUNT(uid)' => 'num'])->from('table.users'))->num;
    }

    public function insert(array $rows): int
    {
        return $this->db->query($this->db->insert('table.users')->rows($rows));
    }

    public function update(array $rows, Query $condition): int
    {
        return $this->db->query($condition->update('table.users')->rows($rows));
    }

    protected function syncCommentAuthor(int $uid, string $screenName): void
    {
        Comment::syncUserAuthor(
            $this->db,
            $uid,
            $screenName,
            null,
            (int) ($this->options->cacheCommentFlush ?? 1)
        );
    }

    public function delete(Query $condition): int
    {
        return $this->db->query($condition->delete('table.users'));
    }

    public function gravatar(int $size = 40, string $rating = 'X', ?string $default = null, ?string $class = null)
    {
        $url = Common::gravatarUrl($this->mail, $size, $rating, $default, $this->request->isSecure());
        echo '<img' . (empty($class) ? '' : ' class="' . $class . '"') . ' src="' . $url . '" alt="' .
            htmlspecialchars($this->screenName, ENT_QUOTES, 'UTF-8') . '" width="' . $size . '" height="' . $size . '" />';
    }

    protected function ___permalink(): string
    {
        return Router::url('author', $this, $this->options->index);
    }

    protected function ___feedUrl(): string
    {
        return Router::url('author', $this, $this->options->feedUrl);
    }

    protected function ___feedRssUrl(): string
    {
        return Router::url('author', $this, $this->options->feedRssUrl);
    }

    protected function ___feedAtomUrl(): string
    {
        return Router::url('author', $this, $this->options->feedAtomUrl);
    }

    protected function ___personalOptions(): Config
    {
        $rows = $this->db->fetchAll($this->db->select()
            ->from('table.options')->where('user = ?', $this->uid));
        $options = [];
        foreach ($rows as $row) {
            $options[$row['name']] = $row['value'];
        }

        return new Config($options);
    }
}
