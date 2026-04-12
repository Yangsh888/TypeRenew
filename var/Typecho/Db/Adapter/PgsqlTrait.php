<?php

namespace Typecho\Db\Adapter;

use Typecho\Db;

trait PgsqlTrait
{
    use QueryTrait;
    private array $pk = [];
    private bool $compatibleInsert = false;
    private ?string $lastInsertTable = null;

    /**
     * @throws SQLException
     */
    public function truncate(string $table, $handle)
    {
        $this->query('TRUNCATE TABLE ' . $this->quoteColumn($table) . ' RESTART IDENTITY', $handle);
    }

    public function parseSelect(array $sql): string
    {
        return $this->buildQuery($sql);
    }

    public function quoteColumn(string $string): string
    {
        return '"' . $string . '"';
    }

    /**
     * @throws SQLException
     */
    protected function prepareQuery(string &$query, $handle, ?string $action = null, ?string $table = null)
    {
        if (Db::INSERT == $action && !empty($table)) {
            $this->compatibleInsert = false;

            if (!isset($this->pk[$table])) {
                $resource = $this->query("SELECT               
  pg_attribute.attname, 
  format_type(pg_attribute.atttypid, pg_attribute.atttypmod) 
FROM pg_index, pg_class, pg_attribute, pg_namespace 
WHERE 
  pg_class.oid = " . $this->quoteValue($table) . "::regclass AND 
  indrelid = pg_class.oid AND 
  nspname = 'public' AND 
  pg_class.relnamespace = pg_namespace.oid AND 
  pg_attribute.attrelid = pg_class.oid AND 
  pg_attribute.attnum = any(pg_index.indkey)
 AND indisprimary", $handle, Db::READ, Db::SELECT, $table);

                $result = $this->fetch($resource);

                if (!empty($result)) {
                    $this->pk[$table] = $result['attname'];
                }
            }

            if (isset($this->pk[$table])) {
                $this->compatibleInsert = true;
                $this->lastInsertTable = $table;
                $query .= ' RETURNING ' . $this->quoteColumn($this->pk[$table]);
            }
        } else {
            $this->lastInsertTable = null;
        }
    }

    /**
     * @throws SQLException
     */
    public function lastInsertId($resource, $handle): int
    {
        $lastTable = $this->lastInsertTable;

        if ($this->compatibleInsert) {
            $result = $this->fetch($resource);
            $pk = $this->pk[$lastTable];

            if (!empty($result) && isset($result[$pk])) {
                return (int) $result[$pk];
            }
        } else {
            $resource = $this->query(
                'SELECT oid FROM pg_class WHERE relname = '
                    . $this->quoteValue($lastTable . '_seq'),
                $handle,
                Db::READ,
                Db::SELECT,
                $lastTable
            );

            $result = $this->fetch($resource);

            if (!empty($result)) {
                $resource = $this->query(
                    'SELECT CURRVAL(' . $this->quoteValue($lastTable . '_seq') . ') AS last_insert_id',
                    $handle,
                    Db::READ,
                    Db::SELECT,
                    $lastTable
                );

                $result = $this->fetch($resource);
                if (!empty($result)) {
                    return (int) $result['last_insert_id'];
                }
            }
        }

        return 0;
    }

    public function getDriver(): string
    {
        return 'pgsql';
    }

    abstract public function query(
        string $query,
        $handle,
        int $op = Db::READ,
        ?string $action = null,
        ?string $table = null
    );

    abstract public function quoteValue(string $string): string;

    abstract public function fetch($resource): ?array;
}
