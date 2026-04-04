<?php

namespace Typecho;

use Typecho\Db\Adapter;
use Typecho\Db\Query;
use Typecho\Db\Exception as DbException;

class Db
{
    public const READ = 1;

    public const WRITE = 2;

    public const SORT_ASC = 'ASC';

    public const SORT_DESC = 'DESC';

    public const INNER_JOIN = 'INNER';

    public const OUTER_JOIN = 'OUTER';

    public const LEFT_JOIN = 'LEFT';

    public const RIGHT_JOIN = 'RIGHT';

    public const SELECT = 'SELECT';

    public const UPDATE = 'UPDATE';

    public const INSERT = 'INSERT';

    public const DELETE = 'DELETE';

    private Adapter $adapter;

    private array $config;

    private array $connectedPool;

    private string $prefix;

    private string $adapterName;

    private static Db $instance;

    public function __construct(string $adapterName, string $prefix = 'typecho_')
    {
        $adapterName = $adapterName == 'Mysql' ? 'Mysqli' : $adapterName;
        $this->adapterName = $adapterName;

        $adapterName = '\Typecho\Db\Adapter\\' . str_replace('_', '\\', $adapterName);

        if (!call_user_func([$adapterName, 'isAvailable'])) {
            throw new DbException("Adapter {$adapterName} is not available");
        }

        $this->prefix = $prefix;

        $this->connectedPool = [];

        $this->config = [
            self::READ => [],
            self::WRITE => []
        ];

        $this->adapter = new $adapterName();
    }

    public function getAdapter(): Adapter
    {
        return $this->adapter;
    }

    public function getAdapterName(): string
    {
        return $this->adapterName;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function addConfig(Config $config, int $op): void
    {
        if ($op & self::READ) {
            $this->config[self::READ][] = $config;
        }

        if ($op & self::WRITE) {
            $this->config[self::WRITE][] = $config;
        }
    }

    public function getConfig(int $op): Config
    {
        if (empty($this->config[$op])) {
            throw new DbException('Missing Database Connection');
        }

        $key = array_rand($this->config[$op]);
        return $this->config[$op][$key];
    }

    public function flushPool(): void
    {
        $this->connectedPool = [];
    }

    public function selectDb(int $op)
    {
        if (!isset($this->connectedPool[$op])) {
            $selectConnectionConfig = $this->getConfig($op);
            $selectConnectionHandle = $this->adapter->connect($selectConnectionConfig);
            $this->connectedPool[$op] = $selectConnectionHandle;
        }

        return $this->connectedPool[$op];
    }

    public function sql(): Query
    {
        return new Query($this->adapter, $this->prefix);
    }

    public function addServer(array $config, int $op): void
    {
        $this->addConfig(Config::factory($config), $op);
        $this->flushPool();
    }

    public function getVersion(int $op = self::READ): string
    {
        return $this->adapter->getVersion($this->selectDb($op));
    }

    public static function set(Db $db): void
    {
        self::$instance = $db;
    }

    public static function get(): Db
    {
        if (empty(self::$instance)) {
            throw new DbException('Missing Database Object');
        }

        return self::$instance;
    }

    public function select(...$ags): Query
    {
        $this->selectDb(self::READ);

        return call_user_func_array([$this->sql(), 'select'], $ags ?: ['*']);
    }

    public function update(string $table): Query
    {
        $this->selectDb(self::WRITE);

        return $this->sql()->update($table);
    }

    public function delete(string $table): Query
    {
        $this->selectDb(self::WRITE);

        return $this->sql()->delete($table);
    }

    public function insert(string $table): Query
    {
        $this->selectDb(self::WRITE);

        return $this->sql()->insert($table);
    }

    public function truncate(string $table): void
    {
        $table = preg_replace("/^table\./", $this->prefix, $table);
        $this->adapter->truncate($table, $this->selectDb(self::WRITE));
        Cache::getInstance()->invalidate($this->normalizeInvalidateTable($table));
    }

    public function query($query, int $op = self::READ, string $action = self::SELECT)
    {
        $table = null;
        $isWriteSql = false;

        if ($query instanceof Query) {
            $action = $query->getAttribute('action');
            $table = $query->getAttribute('table');
            $op = (self::UPDATE == $action || self::DELETE == $action
                || self::INSERT == $action) ? self::WRITE : self::READ;
        } elseif (is_string($query)) {
            $isWriteSql = (bool) preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE|CREATE)\b/i', $query);
            if ($isWriteSql) {
                $op = self::WRITE;
                $table = $this->normalizeInvalidateTable($this->parseWriteTable($query));
            }
        } elseif (!is_string($query)) {
            return $query;
        }

        $handle = $this->selectDb($op);

        $sql = $query instanceof Query ? $query->prepare($query) : $query;

        $resource = $this->adapter->query($sql, $handle, $op, $action, $table);

        if ($action) {
            switch ($action) {
                case self::UPDATE:
                case self::DELETE:
                    Cache::getInstance()->invalidate($table);
                    return $this->adapter->affectedRows($resource, $handle);
                case self::INSERT:
                    Cache::getInstance()->invalidate($table);
                    return $this->adapter->lastInsertId($resource, $handle);
                case self::SELECT:
                default:
                    if ($isWriteSql) {
                        Cache::getInstance()->invalidate($table);
                    }
                    return $resource;
            }
        } else {
            if ($isWriteSql) {
                Cache::getInstance()->invalidate($table);
            }
            return $resource;
        }
    }

    private function parseWriteTable(string $sql): ?string
    {
        $trimmed = trim($sql);
        $patterns = [
            '/^\s*INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*REPLACE\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*UPDATE\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*TRUNCATE(?:\s+TABLE)?\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*DROP\s+TABLE(?:\s+IF\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function normalizeInvalidateTable(?string $table): ?string
    {
        if ($table === null) {
            return null;
        }

        $name = trim(str_replace('`', '', (string) $table));
        if ($name === '') {
            return null;
        }

        if (strpos($name, '.') !== false) {
            $parts = explode('.', $name);
            $name = end($parts) ?: $name;
        }

        if (strpos($name, 'table.') === 0) {
            $name = substr($name, 6);
        }

        if (strpos($name, $this->prefix) === 0) {
            $name = substr($name, strlen($this->prefix));
        }

        $name = strtolower($name);
        return preg_match('/^[a-z0-9_]+$/', $name) ? $name : null;
    }

    public function fetchAll($query, ?callable $filter = null): array
    {
        $cache = Cache::getInstance();
        $cacheKey = $cache->enabled() ? $cache->queryKey($query) : null;
        $locked = false;
        if ($cacheKey) {
            $hit = false;
            $cached = $cache->get($cacheKey, $hit);
            if ($hit && is_array($cached)) {
                return $filter ? array_map($filter, $cached) : $cached;
            }

            $locked = $cache->tryLock($cacheKey, 6);
            if (!$locked) {
                $waited = null;
                if ($cache->waitFor($cacheKey, $waited, 60, 50000) && is_array($waited)) {
                    return $filter ? array_map($filter, $waited) : $waited;
                }
            }
        }

        try {
            $resource = $this->query($query);
            $result = $this->adapter->fetchAll($resource);
            if ($cacheKey) {
                $cache->set($cacheKey, $result);
            }
        } finally {
            if ($cacheKey && $locked) {
                $cache->unlock($cacheKey);
            }
        }

        return $filter ? array_map($filter, $result) : $result;
    }

    public function fetchRow($query, ?callable $filter = null): ?array
    {
        $cache = Cache::getInstance();
        $cacheKey = $cache->enabled() ? $cache->queryKey($query) : null;
        $locked = false;
        if ($cacheKey) {
            $hit = false;
            $cached = $cache->get($cacheKey, $hit);
            if ($hit) {
                if ($cached === null) {
                    return null;
                }
                if (is_array($cached)) {
                    return ($filter ? call_user_func($filter, $cached) : $cached);
                }
            }

            $locked = $cache->tryLock($cacheKey, 6);
            if (!$locked) {
                $waited = null;
                if ($cache->waitFor($cacheKey, $waited, 60, 50000)) {
                    if ($waited === null) {
                        return null;
                    }
                    if (is_array($waited)) {
                        return ($filter ? call_user_func($filter, $waited) : $waited);
                    }
                }
            }
        }

        try {
            $resource = $this->query($query);
            $rows = $this->adapter->fetch($resource);
            if ($cacheKey) {
                $cache->set($cacheKey, $rows);
            }
        } finally {
            if ($cacheKey && $locked) {
                $cache->unlock($cacheKey);
            }
        }

        return ($rows) ?
            ($filter ? call_user_func($filter, $rows) : $rows) :
            null;
    }

    public function fetchObject($query, ?callable $filter = null): ?\stdClass
    {
        $cache = Cache::getInstance();
        $cacheKey = $cache->enabled() ? $cache->queryKey($query) : null;
        $locked = false;
        if ($cacheKey) {
            $hit = false;
            $cached = $cache->get($cacheKey, $hit);
            if ($hit) {
                if ($cached === null) {
                    return null;
                }
                if ($cached instanceof \stdClass) {
                    return ($filter ? call_user_func($filter, $cached) : $cached);
                }
            }

            $locked = $cache->tryLock($cacheKey, 6);
            if (!$locked) {
                $waited = null;
                if ($cache->waitFor($cacheKey, $waited, 60, 50000)) {
                    if ($waited === null) {
                        return null;
                    }
                    if ($waited instanceof \stdClass) {
                        return ($filter ? call_user_func($filter, $waited) : $waited);
                    }
                }
            }
        }

        try {
            $resource = $this->query($query);
            $rows = $this->adapter->fetchObject($resource);
            if ($cacheKey) {
                $cache->set($cacheKey, $rows);
            }
        } finally {
            if ($cacheKey && $locked) {
                $cache->unlock($cacheKey);
            }
        }

        return ($rows) ?
            ($filter ? call_user_func($filter, $rows) : $rows) :
            null;
    }
}
