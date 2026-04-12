<?php

namespace Typecho\Db\Adapter;

trait MysqlTrait
{
    use QueryTrait;

    protected function resolveCollation(string $charset): ?string
    {
        return match (strtolower($charset)) {
            'utf8mb4' => 'utf8mb4_unicode_ci',
            'utf8' => 'utf8_unicode_ci',
            default => null,
        };
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
