<?php

namespace Widget\Metas;

trait EditTrait
{
    private function isDuplicateRelationshipError(\Throwable $e): bool
    {
        $code = (string) $e->getCode();
        $message = strtolower($e->getMessage());

        return $code === '1062'
            || $code === '23000'
            || $code === '23505'
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint')
            || str_contains($message, '1062')
            || str_contains($message, '23000')
            || str_contains($message, '23505');
    }

    public function getMaxOrder(string $type, int $parent = 0): int
    {
        return $this->db->fetchObject($this->select(['MAX(order)' => 'maxOrder'])
            ->where('type = ? AND parent = ?', $type, $parent))->maxOrder ?? 0;
    }

    public function sort(array $metas, string $type)
    {
        foreach ($metas as $sort => $mid) {
            $this->update(
                ['order' => $sort + 1],
                $this->db->sql()->where('mid = ?', $mid)->where('type = ?', $type)
            );
        }
    }

    public function merge(int $mid, string $type, array $metas)
    {
        // 目标已关联的内容
        $contents = array_column($this->db->fetchAll($this->db->select('cid')
            ->from('table.relationships')
            ->where('mid = ?', $mid)), 'cid');

        // 待合并的源 (排除目标自身)
        $sources = [];
        foreach ($metas as $meta) {
            $meta = (int) $meta;
            if ($meta !== $mid) {
                $sources[$meta] = $meta;
            }
        }
        $sources = array_values($sources);

        if (empty($sources)) {
            return;
        }

        // 一次性取出全部源的关联内容, 避免逐个源查询
        $sourceContents = array_column($this->db->fetchAll($this->db->select('cid')
            ->from('table.relationships')
            ->where('mid IN ?', $sources)), 'cid');

        // 去重后真正需要补到目标的内容 (源有、目标无)
        $existing = array_fill_keys(array_map('intval', $contents), true);
        $pending = [];
        foreach ($sourceContents as $cid) {
            $cid = (int) $cid;
            if (!isset($existing[$cid])) {
                $existing[$cid] = true;
                $pending[] = $cid;
            }
        }

        // 构造器仅支持单行 INSERT, 但此处规模已是去重后的缺失数, 不再被源数量放大
        foreach ($pending as $cid) {
            try {
                $this->db->query($this->db->insert('table.relationships')
                    ->rows(['mid' => $mid, 'cid' => $cid]));
            } catch (\Throwable $e) {
                if (!$this->isDuplicateRelationshipError($e)) {
                    throw $e;
                }
            }
        }

        // 批量清理源: 关系 / meta 行 / 子级改挂
        $this->db->query($this->db->delete('table.relationships')->where('mid IN ?', $sources));
        $this->delete($this->db->sql()->where('mid IN ? AND type = ?', $sources, $type));
        $this->update(['parent' => $mid], $this->db->sql()->where('parent IN ?', $sources));

        $num = $this->db->fetchObject($this->db
            ->select(['COUNT(mid)' => 'num'])->from('table.relationships')
            ->where('table.relationships.mid = ?', $mid))->num;

        $this->update(['count' => $num], $this->db->sql()->where('mid = ?', $mid));
    }

    protected function refreshCountByTypeAndStatus(int $mid, string $type, string $status = 'publish')
    {
        $select = $this->db->select(['COUNT(table.contents.cid)' => 'num'])->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid = ?', $mid)
            ->where('table.contents.type = ?', $type)
            ->where('table.contents.status = ?', $status);

        if ($status === 'publish') {
            $select->where('table.contents.created < ?', $this->options->time);
        }

        $num = $this->db->fetchObject($select)->num;

        $this->db->query($this->db->update('table.metas')->rows(['count' => $num])
            ->where('mid = ?', $mid));
    }

    /**
     * 批量重算多个 mid 的 count, 避免逐项 COUNT+UPDATE 造成的 N+1
     *
     * @param int[] $mids 需要刷新的分类/标签主键
     */
    protected function refreshCountBatch(array $mids, string $type, string $status = 'publish')
    {
        $mids = array_values(array_unique(array_map('intval', $mids)));

        if (empty($mids)) {
            return;
        }

        $select = $this->db->select(['table.relationships.mid' => 'mid', 'COUNT(table.contents.cid)' => 'num'])
            ->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid IN ?', $mids)
            ->where('table.contents.type = ?', $type)
            ->where('table.contents.status = ?', $status);

        if ($status === 'publish') {
            $select->where('table.contents.created < ?', $this->options->time);
        }

        $select->group('table.relationships.mid');

        // 默认全部为 0, 再用聚合结果覆盖 (没有关联内容的 mid 不会出现在结果中)
        $counts = array_fill_keys($mids, 0);
        foreach ($this->db->fetchAll($select) as $row) {
            $counts[(int) $row['mid']] = (int) $row['num'];
        }

        // 按 count 值归组, 相同值的 mid 合并为一条 UPDATE ... WHERE mid IN (...)
        $groups = [];
        foreach ($counts as $mid => $num) {
            $groups[$num][] = $mid;
        }

        foreach ($groups as $num => $groupMids) {
            $this->db->query($this->db->update('table.metas')->rows(['count' => $num])
                ->where('mid IN ?', $groupMids));
        }
    }
}
