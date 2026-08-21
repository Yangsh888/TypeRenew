<?php

namespace Typecho\Db\Adapter;

trait SQLiteTrait
{
    use QueryTrait;

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

    public function parseSelect(array $sql): string
    {
        return $this->buildQuery($sql);
    }

    public function getDriver(): string
    {
        return 'sqlite';
    }
}
