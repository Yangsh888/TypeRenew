<?php

namespace Typecho\Db\Adapter;

trait QueryTrait
{
    private function buildQuery(array $sql): string
    {
        if (!empty($sql['join'])) {
            foreach ($sql['join'] as $val) {
                [$table, $condition, $op] = $val;
                $sql['table'] = "{$sql['table']} {$op} JOIN {$table} ON {$condition}";
            }
        }

        if (isset($sql['offset']) && !isset($sql['limit'])) {
            $sql['limit'] = PHP_INT_MAX;
        }

        $sql['limit'] = isset($sql['limit']) ? ' LIMIT ' . $sql['limit'] : '';
        $sql['offset'] = isset($sql['offset']) ? ' OFFSET ' . $sql['offset'] : '';

        $distinct = !empty($sql['distinct']) ? 'DISTINCT ' : '';

        return 'SELECT ' . $distinct . $sql['fields'] . ' FROM ' . $sql['table'] .
            $sql['where'] . $sql['group'] . $sql['having'] . $sql['order'] . $sql['limit'] . $sql['offset'];
    }
}
