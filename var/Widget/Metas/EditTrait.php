<?php

namespace Widget\Metas;

trait EditTrait
{

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
        $contents = array_column($this->db->fetchAll($this->db->select('cid')
            ->from('table.relationships')
            ->where('mid = ?', $mid)), 'cid');

        foreach ($metas as $meta) {
            if ($mid != $meta) {
                $existsContents = array_column($this->db->fetchAll($this->db
                    ->select('cid')->from('table.relationships')
                    ->where('mid = ?', $meta)), 'cid');

                $where = $this->db->sql()->where('mid = ? AND type = ?', $meta, $type);
                $this->delete($where);
                $diffContents = array_diff($existsContents, $contents);
                $this->db->query($this->db->delete('table.relationships')->where('mid = ?', $meta));

                foreach ($diffContents as $content) {
                    $this->db->query($this->db->insert('table.relationships')
                        ->rows(['mid' => $mid, 'cid' => $content]));
                    $contents[] = $content;
                }

                $this->update(['parent' => $mid], $this->db->sql()->where('parent = ?', $meta));
                unset($existsContents);
            }
        }

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
}
