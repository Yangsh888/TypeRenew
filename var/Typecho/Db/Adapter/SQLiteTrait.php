<?php

namespace Typecho\Db\Adapter;

trait SQLiteTrait
{
    use QueryTrait;

    private bool $isSQLite2 = false;

    /**
     * @throws SQLException
     */
    public function truncate(string $table, $handle)
    {
        $this->query('DELETE FROM ' . $this->quoteColumn($table), $handle);
    }

    public function quoteColumn(string $string): string
    {
        return '"' . $string . '"';
    }

    private function filterColumnName(array $result): array
    {
        if (empty($result)) {
            return $result;
        }

        $tResult = [];

        foreach ($result as $key => $val) {
            if (false !== ($pos = strpos($key, '.'))) {
                $key = substr($key, $pos + 1);
            }

            $tResult[trim($key, '"')] = $val;
        }

        return $tResult;
    }

    private function filterCountQuery(string $sql): string
    {
        if (preg_match("/SELECT\s+COUNT\(DISTINCT\s+([^\)]+)\)\s+(AS\s+[^\s]+)?\s*FROM\s+(.+)/is", $sql, $matches)) {
            return 'SELECT COUNT(' . $matches[1] . ') ' . $matches[2] . ' FROM SELECT DISTINCT '
                . $matches[1] . ' FROM ' . $matches[3];
        }

        return $sql;
    }

    public function parseSelect(array $sql): string
    {
        $query = $this->buildQuery($sql);

        if ($this->isSQLite2) {
            $query = $this->filterCountQuery($query);
        }

        return $query;
    }

    public function getDriver(): string
    {
        return 'sqlite';
    }
}
