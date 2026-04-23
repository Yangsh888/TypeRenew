<?php

namespace Typecho\Db\Adapter;

use Utils\DbInfo;

trait MysqlTrait
{
    use QueryTrait;

    protected function resolveCollation(string $charset, string $serverVersion = '', ?string $existingCollation = null): ?string
    {
        return DbInfo::resolveMysqlCollation($charset, $serverVersion, $existingCollation);
    }

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
