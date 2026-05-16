<?php

namespace Widget\Base;

interface RowFilterInterface
{
    public function filter(array $row): array;
}
