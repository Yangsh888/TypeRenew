<?php

namespace Widget\Base;

/**
 * 行过滤器接口
 */
interface RowFilterInterface
{
    public function filter(array $row): array;
}
