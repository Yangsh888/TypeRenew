<?php

namespace Widget\Base;

use Typecho\Common;
use Typecho\Db\Query;
use Typecho\Router;
use Typecho\Router\ParamsDelegateInterface;
use Widget\Base;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 描述性数据组件
 *
 * @property int $mid
 * @property string $name
 * @property string $title
 * @property string $slug
 * @property string $type
 * @property string $description
 * @property int $count
 * @property int $order
 * @property int $parent
 * @property-read string $theId
 * @property-read string $url
 * @property-read string $permalink
 * @property-read string[] $directory
 * @property-read string $feedUrl
 * @property-read string $feedRssUrl
 * @property-read string $feedAtomUrl
 */
class Metas extends Base implements QueryInterface, RowFilterInterface, PrimaryKeyInterface, ParamsDelegateInterface
{
    public function getPrimaryKey(): string
    {
        return 'mid';
    }

    public function getRouterParam(string $key): string
    {
        return match ($key) {
            'mid' => (string) $this->mid,
            'slug' => urlencode($this->slug),
            'directory' => implode('/', array_map('urlencode', $this->directory)),
            default => '{' . $key . '}',
        };
    }

    public function size(Query $condition): int
    {
        return $this->db->fetchObject($condition->select(['COUNT(mid)' => 'num'])->from('table.metas'))->num;
    }

    public function push(array $value): array
    {
        $value = $this->filter($value);
        return parent::push($value);
    }

    public function filter(array $row): array
    {
        return Metas::pluginHandle()->filter('filter', $row, $this);
    }

    public function update(array $rows, Query $condition): int
    {
        return $this->db->query($condition->update('table.metas')->rows($rows));
    }

    public function select(...$fields): Query
    {
        return $this->db->select(...$fields)->from('table.metas');
    }

    public function delete(Query $condition): int
    {
        return $this->db->query($condition->delete('table.metas'));
    }

    public function insert(array $rows): int
    {
        return $this->db->query($this->db->insert('table.metas')->rows($rows));
    }

    public function scanTags($inputTags)
    {
        $tags = is_array($inputTags) ? $inputTags : [$inputTags];
        $tags = array_values(array_filter($tags, static fn($tag) => !empty($tag)));

        if (empty($tags)) {
            return is_array($inputTags) ? [] : null;
        }

        $existing = [];
        foreach (
            $this->db->fetchAll($this->select('mid', 'name')
                ->where('type = ?', 'tag')
                ->where('name IN ?', $tags)) as $row
        ) {
            $existing[$row['name']] = $row['mid'];
        }

        $result = [];

        foreach ($tags as $tag) {
            if (isset($existing[$tag])) {
                $result[] = $existing[$tag];
                continue;
            }

            $slug = Common::slugName($tag);

            if ($slug) {
                $mid = $this->insert([
                    'name'  => $tag,
                    'slug'  => $slug,
                    'type'  => 'tag',
                    'count' => 0,
                    'order' => 0,
                ]);
                $existing[$tag] = $mid;
                $result[] = $mid;
            }
        }

        return is_array($inputTags) ? $result : current($result);
    }

    public function clearTags()
    {
        $tags = array_column($this->db->fetchAll($this->select('mid')
            ->where('type = ? AND count = ?', 'tag', 0)), 'mid');

        if (empty($tags)) {
            return;
        }

        $usedTags = array_column($this->db->fetchAll($this->db->select('mid')
            ->from('table.relationships')
            ->where('mid IN ?', $tags)
            ->group('mid')), 'mid');

        $emptyTags = array_diff($tags, array_map('intval', $usedTags));

        if (!empty($emptyTags)) {
            $this->db->query($this->db->delete('table.metas')
                ->where('mid IN ?', array_values($emptyTags)));
        }
    }

    protected function ___theId(): string
    {
        return $this->type . '-' . $this->mid;
    }

    protected function ___title(): string
    {
        return $this->name;
    }

    protected function ___directory(): array
    {
        return [];
    }

    protected function ___permalink(): string
    {
        return Router::url($this->type, $this, $this->options->index);
    }

    protected function ___url(): string
    {
        return $this->permalink;
    }

    protected function ___feedUrl(): string
    {
        return Router::url($this->type, $this, $this->options->feedUrl);
    }

    protected function ___feedRssUrl(): string
    {
        return Router::url($this->type, $this, $this->options->feedRssUrl);
    }

    protected function ___feedAtomUrl(): string
    {
        return Router::url($this->type, $this, $this->options->feedAtomUrl);
    }
}
