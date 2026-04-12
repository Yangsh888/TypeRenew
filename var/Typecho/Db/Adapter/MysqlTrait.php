<?php

namespace Typecho\Db\Adapter;

trait MysqlTrait
{
    use QueryTrait;

    /**
     * @throws SQLException
     */
    public function truncate(string $table, $handle)
    {
        $this->query('TRUNCATE TABLE ' . $this->quoteColumn($table), $handle);
    }

    public function parseSelect(array $sql): string
    {
        return $this->buildQuery($sql);
    }

    public function getDriver(): string
    {
        return 'mysql';
    }
}
