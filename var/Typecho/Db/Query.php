<?php

namespace Typecho\Db;

use Typecho\Db;

class Query
{
    private const KEYWORDS = '*PRIMARY|AND|OR|LIKE|ILIKE|BINARY|BY|DISTINCT|AS|IN|NOT|IS|NULL';

    private static array $default = [
        'action' => null,
        'table'  => null,
        'fields' => '*',
        'join'   => [],
        'where'  => null,
        'limit'  => null,
        'offset' => null,
        'order'  => null,
        'group'  => null,
        'having' => null,
        'rows'   => [],
    ];

    private Adapter $adapter;

    private array $sqlPreBuild;

    private string $prefix;

    private array $params = [];

    public function __construct(Adapter $adapter, string $prefix)
    {
        $this->adapter = &$adapter;
        $this->prefix = $prefix;

        $this->sqlPreBuild = self::$default;
    }

    public static function setDefault(array $default): void
    {
        self::$default = array_merge(self::$default, $default);
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getAttribute(string $attributeName): ?string
    {
        return $this->sqlPreBuild[$attributeName] ?? null;
    }

    public function cleanAttribute(string $attributeName): Query
    {
        if (isset($this->sqlPreBuild[$attributeName])) {
            $this->sqlPreBuild[$attributeName] = self::$default[$attributeName];
        }
        return $this;
    }

    public function join(string $table, string $condition, string $op = Db::INNER_JOIN): Query
    {
        $this->sqlPreBuild['join'][] = [$this->filterPrefix($table), $this->filterColumn($condition), $op];
        return $this;
    }

    private function filterPrefix(string $string): string
    {
        return (0 === strpos($string, 'table.')) ? substr_replace($string, $this->prefix, 0, 6) : $string;
    }

    private function filterColumn(string $str): string
    {
        $str = $str . ' 0';
        $length = strlen($str);
        $lastIsAlnum = false;
        $result = '';
        $word = '';
        $split = '';
        $quotes = 0;

        for ($i = 0; $i < $length; $i++) {
            $cha = $str[$i];

            if (ctype_alnum($cha) || false !== strpos('_*', $cha)) {
                if (!$lastIsAlnum) {
                    if (
                        $quotes > 0 && !ctype_digit($word) && '.' != $split
                        && false === strpos(self::KEYWORDS, strtoupper($word))
                    ) {
                        $word = $this->adapter->quoteColumn($word);
                    } elseif ('.' == $split && 'table' == $word) {
                        $word = $this->prefix;
                        $split = '';
                    }

                    $result .= $word . $split;
                    $word = '';
                    $quotes = 0;
                }

                $word .= $cha;
                $lastIsAlnum = true;
            } else {
                if ($lastIsAlnum) {
                    if (0 == $quotes) {
                        if (false !== strpos(' ,)=<>.+-*/', $cha)) {
                            $quotes = 1;
                        } elseif ('(' == $cha) {
                            $quotes = - 1;
                        }
                    }

                    $split = '';
                }

                $split .= $cha;
                $lastIsAlnum = false;
            }
        }

        return $result;
    }

    public function where(...$args): Query
    {
        [$condition] = $args;
        $condition = str_replace('?', "%s", $this->filterColumn($condition));
        $operator = empty($this->sqlPreBuild['where']) ? ' WHERE ' : ' AND';

        if (count($args) <= 1) {
            $this->sqlPreBuild['where'] .= $operator . ' (' . $condition . ')';
        } else {
            array_shift($args);
            $this->sqlPreBuild['where'] .= $operator . ' (' . vsprintf($condition, $this->quoteValues($args)) . ')';
        }

        return $this;
    }

    protected function quoteValues(array $values): array
    {
        foreach ($values as &$value) {
            if (is_array($value)) {
                $value = '(' . implode(',', array_map([$this, 'quoteValue'], $value)) . ')';
            } else {
                $value = $this->quoteValue($value);
            }
        }

        return $values;
    }

    public function quoteValue(mixed $value): string
    {
        $this->params[] = $value;
        return '#param:' . (count($this->params) - 1) . '#';
    }

    public function orWhere(...$args): Query
    {
        [$condition] = $args;
        $condition = str_replace('?', "%s", $this->filterColumn($condition));
        $operator = empty($this->sqlPreBuild['where']) ? ' WHERE ' : ' OR';

        if (func_num_args() <= 1) {
            $this->sqlPreBuild['where'] .= $operator . ' (' . $condition . ')';
        } else {
            array_shift($args);
            $this->sqlPreBuild['where'] .= $operator . ' (' . vsprintf($condition, $this->quoteValues($args)) . ')';
        }

        return $this;
    }

    public function limit(int $limit): Query
    {
        $this->sqlPreBuild['limit'] = $limit;
        return $this;
    }

    public function offset(int $offset): Query
    {
        $this->sqlPreBuild['offset'] = $offset;
        return $this;
    }

    public function page(int $page, int $pageSize): Query
    {
        $safePageSize = max($pageSize, 1);
        $this->sqlPreBuild['limit'] = $safePageSize;
        $this->sqlPreBuild['offset'] = (max($page, 1) - 1) * $safePageSize;
        return $this;
    }

    public function rows(array $rows): Query
    {
        foreach ($rows as $key => $row) {
            $this->sqlPreBuild['rows'][$this->filterColumn($key)]
                = is_null($row) ? 'NULL' : $this->adapter->quoteValue($row);
        }
        return $this;
    }

    public function expression(string $key, string $value, bool $escape = true): Query
    {
        $this->sqlPreBuild['rows'][$this->filterColumn($key)] = $escape ? $this->filterColumn($value) : $value;
        return $this;
    }

    public function order(string $orderBy, string $sort = Db::SORT_ASC): Query
    {
        if (empty($this->sqlPreBuild['order'])) {
            $this->sqlPreBuild['order'] = ' ORDER BY ';
        } else {
            $this->sqlPreBuild['order'] .= ', ';
        }

        $this->sqlPreBuild['order'] .= $this->filterColumn($orderBy) . (empty($sort) ? null : ' ' . $sort);
        return $this;
    }

    public function group(string $key): Query
    {
        $this->sqlPreBuild['group'] = ' GROUP BY ' . $this->filterColumn($key);
        return $this;
    }

    public function having(string $condition, ...$args): Query
    {
        $condition = str_replace('?', "%s", $this->filterColumn($condition));
        $operator = empty($this->sqlPreBuild['having']) ? ' HAVING ' : ' AND';

        if (count($args) == 0) {
            $this->sqlPreBuild['having'] .= $operator . ' (' . $condition . ')';
        } else {
            $this->sqlPreBuild['having'] .= $operator . ' (' . vsprintf($condition, $this->quoteValues($args)) . ')';
        }

        return $this;
    }

    public function select(...$args): Query
    {
        $this->sqlPreBuild['action'] = Db::SELECT;

        $this->sqlPreBuild['fields'] = $this->getColumnFromParameters($args);
        return $this;
    }

    public function distinct(bool $distinct = true): Query
    {
        $this->sqlPreBuild['distinct'] = $distinct;
        return $this;
    }

    private function getColumnFromParameters(array $parameters): string
    {
        $fields = [];

        foreach ($parameters as $value) {
            if (is_array($value)) {
                foreach ($value as $key => $val) {
                    $fields[] = $key . ' AS ' . $val;
                }
            } else {
                $fields[] = $value;
            }
        }

        return $this->filterColumn(implode(' , ', $fields));
    }

    public function from(string $table): Query
    {
        $this->sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    public function update(string $table): Query
    {
        $this->sqlPreBuild['action'] = Db::UPDATE;
        $this->sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    public function delete(string $table): Query
    {
        $this->sqlPreBuild['action'] = Db::DELETE;
        $this->sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    public function insert(string $table): Query
    {
        $this->sqlPreBuild['action'] = Db::INSERT;
        $this->sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    public function prepare(string $query): string
    {
        $params = $this->params;
        $adapter = $this->adapter;

        return preg_replace_callback("/#param:([0-9]+)#/", function ($matches) use ($params, $adapter) {
            if (array_key_exists($matches[1], $params)) {
                return is_null($params[$matches[1]]) ? 'NULL' : $adapter->quoteValue($params[$matches[1]]);
            } else {
                return $matches[0];
            }
        }, $query);
    }

    public function __toString(): string
    {
        switch ($this->sqlPreBuild['action']) {
            case Db::SELECT:
                return $this->adapter->parseSelect($this->sqlPreBuild);
            case Db::INSERT:
                return 'INSERT INTO '
                    . $this->sqlPreBuild['table']
                    . '(' . implode(' , ', array_keys($this->sqlPreBuild['rows'])) . ')'
                    . ' VALUES '
                    . '(' . implode(' , ', array_values($this->sqlPreBuild['rows'])) . ')'
                    . $this->sqlPreBuild['limit'];
            case Db::DELETE:
                return 'DELETE FROM '
                    . $this->sqlPreBuild['table']
                    . $this->sqlPreBuild['where'];
            case Db::UPDATE:
                $columns = [];
                if (isset($this->sqlPreBuild['rows'])) {
                    foreach ($this->sqlPreBuild['rows'] as $key => $val) {
                        $columns[] = "$key = $val";
                    }
                }

                return 'UPDATE '
                    . $this->sqlPreBuild['table']
                    . ' SET ' . implode(' , ', $columns)
                    . $this->sqlPreBuild['where'];
            default:
                return '';
        }
    }
}
