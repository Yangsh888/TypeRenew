<?php

namespace Widget\Base;

use Typecho\Config;
trait TreeTrait
{
    private array $treeRows = [];

    private array $top = [];

    private array $map = [];

    private array $orders = [];

    private array $childNodes = [];

    private array $parents = [];

    public function levelsAlt(...$args)
    {
        $this->altBy($this->levels, ...$args);
    }

    public function getAllParentsSlug(int $id): array
    {
        $parents = [];

        if (isset($this->parents[$id])) {
            foreach ($this->parents[$id] as $parent) {
                if (isset($this->map[$parent])) {
                    $parents[] = $this->map[$parent]['slug'];
                }
            }
        }

        return $parents;
    }

    public function getAllChildIds(int $id): array
    {
        return $this->childNodes[$id] ?? [];
    }

    public function getChildIds(int $id): array
    {
        return $id > 0 ? ($this->treeRows[$id] ?? []) : $this->top;
    }

    public function getAllParents(int $id): array
    {
        $parents = [];

        if (isset($this->parents[$id])) {
            foreach ($this->parents[$id] as $parent) {
                if (isset($this->map[$parent])) {
                    $parents[] = $this->map[$parent];
                }
            }
        }

        return $parents;
    }

    public function getRows(array $ids, int $ignore = 0): array
    {
        $result = [];

        if (!empty($ids)) {
            foreach ($ids as $id) {
                if (isset($this->map[$id]) && (!$ignore || ($ignore != $id && !$this->hasParent($id, $ignore)))) {
                    $result[] = $this->map[$id];
                }
            }
        }

        return $result;
    }

    public function getRow(int $id): ?array
    {
        return $this->map[$id] ?? null;
    }

    public function hasParent($id, $parentId): bool
    {
        if (isset($this->parents[$id])) {
            foreach ($this->parents[$id] as $parent) {
                if ($parent == $parentId) {
                    return true;
                }
            }
        }

        return false;
    }

    abstract protected function initTreeRows(): array;

    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault('ignore=0&current=');

        $this->treeRows = [];
        $this->top = [];
        $this->map = [];
        $this->orders = [];
        $this->childNodes = [];
        $this->parents = [];

        $rows = $this->initTreeRows();
        $pk = $this->getPrimaryKey();

        usort($rows, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        foreach ($rows as $row) {
            $row['levels'] = 0;
            $this->map[$row[$pk]] = $row;
        }

        foreach ($this->map as $id => $row) {
            $parent = $row['parent'];

            if (0 != $parent && isset($this->map[$parent])) {
                $this->treeRows[$parent][] = $id;
            } else {
                $this->top[] = $id;
            }
        }

        $this->levelWalkCallback($this->top);
        $this->map = array_map([$this, 'filter'], $this->map);
    }

    protected function ___directory(): array
    {
        $directory = $this->getAllParentsSlug($this->{$this->getPrimaryKey()});
        $directory[] = $this->slug;
        return $directory;
    }

    protected function ___children(): array
    {
        $id = $this->{$this->getPrimaryKey()};
        return $this->getRows($this->getChildIds($id));
    }

    private function levelWalkCallback(array $rows, array $parents = [])
    {
        foreach ($parents as $parent) {
            if (!isset($this->childNodes[$parent])) {
                $this->childNodes[$parent] = [];
            }

            $this->childNodes[$parent] = array_merge($this->childNodes[$parent], $rows);
        }

        foreach ($rows as $id) {
            if (!isset($this->map[$id])) {
                continue;
            }

            $this->orders[] = $id;
            $parent = $this->map[$id]['parent'];

            if (0 != $parent && isset($this->map[$parent])) {
                $levels = $this->map[$parent]['levels'] + 1;
                $this->map[$id]['levels'] = $levels;
            }

            $this->parents[$id] = $parents;

            if (!empty($this->treeRows[$id])) {
                $new = $parents;
                $new[] = $id;
                $this->levelWalkCallback($this->treeRows[$id], $new);
            }
        }
    }
}
