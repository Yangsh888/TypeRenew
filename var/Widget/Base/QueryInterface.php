<?php

namespace Widget\Base;

use Typecho\Db\Query;

/**
 * Base Query Interface
 */
interface QueryInterface
{
    public function select(...$fields): Query;

    public function size(Query $condition): int;

    public function insert(array $rows): int;

    public function update(array $rows, Query $condition): int;

    public function delete(Query $condition): int;
}
