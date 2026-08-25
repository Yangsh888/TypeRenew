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

    public const LEFT_JOIN = 'LEFT';

    public const RIGHT_JOIN = 'RIGHT';

    public const OUTER_JOIN = 'OUTER';

    public const SELECT = 'SELECT';

    public const UPDATE = 'UPDATE';

    public const INSERT = 'INSERT';

    public const DELETE = 'DELETE';

    private Adapter $adapter;

    private array $config;

    private array $connectedPool;

    private string $prefix;

    private string $adapterName;

    private bool $transactionActive = false;

    private array $pendingInvalidations = [];

    private bool $invalidateAllOnCommit = false;

    private static Db $instance;

    public function __construct(string $adapterName, string $prefix = 'typecho_')
    {
        $adapterName = $adapterName == 'Mysql' ? 'Mysqli' : $adapterName;
        $this->adapterName = $adapterName;

        $adapterName = '\Typecho\Db\Adapter\\' . str_replace('_', '\\', $adapterName);

        if (!$adapterName::isAvailable()) {
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
        if ($this->transactionActive) {
            $op = self::WRITE;
        }

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
        $this->invalidateCache($this->normalizeInvalidateTable($table));
    }

    public function query($query, int $op = self::READ, string $action = self::SELECT)
    {
        $table = null;
        $isWriteSql = false;
        $forceWriteConnection = false;
        $transactionCommand = null;

        if ($query instanceof Query) {
            $action = $query->getAttribute('action');
            $table = $this->normalizeInvalidateTable($query->getAttribute('table'));
            $op = (self::UPDATE == $action || self::DELETE == $action
                || self::INSERT == $action) ? self::WRITE : self::READ;
        } elseif (is_string($query)) {
            $isWriteSql = (bool) preg_match('/^\s*(?:(?:\/\*.*?\*\/|--[^\r\n]*(?:\r?\n|$)|#[^\r\n]*(?:\r?\n|$))\s*)*(?:WITH\b[\s\S]*?\b(INSERT|UPDATE|DELETE|REPLACE)\b|INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE|CREATE)\b/i', $query);
            $transactionCommand = $this->transactionCommand($query);
            $forceWriteConnection = (bool) preg_match(
                '/^\s*(?:START\s+TRANSACTION|BEGIN|COMMIT|ROLLBACK|SAVEPOINT|RELEASE\s+SAVEPOINT|LOCK\s+TABLES|UNLOCK\s+TABLES|SET\s+TRANSACTION)\b/i',
                $query
            );
            if ($isWriteSql) {
                $op = self::WRITE;
                $table = $this->normalizeInvalidateTable($this->parseWriteTable($query));
            } elseif ($forceWriteConnection) {
                $op = self::WRITE;
            }
        } elseif (!is_string($query)) {
            return $query;
        }

        $handle = $this->selectDb($op);

        $sql = $query instanceof Query ? $query->prepare($query) : $query;

        try {
            $resource = $this->adapter->query($sql, $handle, $op, $action, $table);
        } catch (\Throwable $e) {
            if ($transactionCommand === 'commit' || $transactionCommand === 'rollback') {
                $this->transactionActive = false;
                $this->flushPendingInvalidations();
            }

            throw $e;
        }

        if ($transactionCommand === 'begin') {
            $this->transactionActive = true;
        } elseif ($transactionCommand === 'commit') {
            $this->transactionActive = false;
            $this->flushPendingInvalidations();
        } elseif ($transactionCommand === 'rollback') {
            $this->transactionActive = false;
            $this->pendingInvalidations = [];
            $this->invalidateAllOnCommit = false;
        }

        if ($action) {
            switch ($action) {
                case self::UPDATE:
                case self::DELETE:
                    $this->invalidateCache($table);
                    return $this->adapter->affectedRows($resource, $handle);
                case self::INSERT:
                    $this->invalidateCache($table);
                    return $this->adapter->lastInsertId($resource, $handle);
                case self::SELECT:
                default:
                    if ($isWriteSql) {
                        $this->invalidateCache($table);
                    }
                    return $resource;
            }
        } else {
            if ($isWriteSql) {
                $this->invalidateCache($table);
            }
            return $resource;
        }
    }

    private function parseWriteTable(string $sql): ?string
    {
        $trimmed = preg_replace('/^\s*(?:(?:\/\*.*?\*\/|--[^\r\n]*(?:\r?\n|$)|#[^\r\n]*(?:\r?\n|$))\s*)*/s', '', $sql) ?? trim($sql);
        if (preg_match('/^\s*WITH\b[\s\S]*?\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?([a-zA-Z0-9_]+)`?/i', $trimmed, $matches)) {
            return $matches[1];
        }
        $patterns = [
            '/^\s*INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*REPLACE\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*UPDATE\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*TRUNCATE(?:\s+TABLE)?\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*DROP\s+TABLE(?:\s+IF\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:[`"]?[a-zA-Z0-9_]+[`"]?\s+)?ON\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i'
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

        if (strpos($name, 'table.') === 0) {
            $name = $this->prefix . substr($name, 6);
        } elseif (strpos($name, '.') !== false) {
            $parts = explode('.', $name);
            $name = end($parts) ?: $name;
        }

        $name = strtolower($name);
        return preg_match('/^[a-z0-9_]+$/', $name) ? $name : null;
    }

    private function transactionCommand(string $sql): ?string
    {
        $sql = rtrim(trim($sql), "; \t\n\r\0\x0B");

        if (preg_match('/^(?:START\s+TRANSACTION|BEGIN(?:\s+(?:WORK|TRANSACTION))?)$/i', $sql)) {
            return 'begin';
        }

        if (preg_match('/^COMMIT(?:\s+WORK)?$/i', $sql)) {
            return 'commit';
        }

        if (preg_match('/^ROLLBACK(?:\s+WORK)?$/i', $sql)) {
            return 'rollback';
        }

        return null;
    }

    private function invalidateCache(?string $table): void
    {
        if (!$this->transactionActive) {
            Cache::getInstance()->invalidate($table);
            return;
        }

        if ($table === null) {
            $this->invalidateAllOnCommit = true;
            $this->pendingInvalidations = [];
            return;
        }

        if (!$this->invalidateAllOnCommit) {
            $this->pendingInvalidations[$table] = true;
        }
    }

    private function flushPendingInvalidations(): void
    {
        $cache = Cache::getInstance();

        if ($this->invalidateAllOnCommit) {
            $cache->invalidate();
        } else {
            foreach (array_keys($this->pendingInvalidations) as $table) {
                $cache->invalidate($table);
            }
        }

        $this->pendingInvalidations = [];
        $this->invalidateAllOnCommit = false;
    }

    private function cacheKey($query, ?int &$ttl = null, string $shape = 'all'): ?string
    {
        $ttl = null;
        $cache = Cache::getInstance();
        if (!$cache->enabled() || $this->transactionActive) {
            return null;
        }

        $key = $cache->queryKey($query, $ttl);
        return $key === null ? null : $shape . ':' . $key;
    }

    public function fetchAll($query, ?callable $filter = null): array
    {
        $cache = Cache::getInstance();
        $cacheTtl = null;
        $cacheKey = $this->cacheKey($query, $cacheTtl, 'all');
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
                if ($cache->waitFor($cacheKey, $waited, 6, 50000) && is_array($waited)) {
                    return $filter ? array_map($filter, $waited) : $waited;
                }
            }
        }

        try {
            $resource = $this->query($query);
            $result = $this->adapter->fetchAll($resource);
            if ($cacheKey) {
                $cache->set($cacheKey, $result, $cacheTtl);
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
        $cacheTtl = null;
        $cacheKey = $this->cacheKey($query, $cacheTtl, 'row');
        $locked = false;
        if ($cacheKey) {
            $hit = false;
            $cached = $cache->get($cacheKey, $hit);
            if ($hit) {
                if ($cached === null) {
                    return null;
                }
                if (is_array($cached)) {
                    return $filter ? $filter($cached) : $cached;
                }
            }

            $locked = $cache->tryLock($cacheKey, 6);
            if (!$locked) {
                $waited = null;
                if ($cache->waitFor($cacheKey, $waited, 6, 50000)) {
                    if ($waited === null) {
                        return null;
                    }
                    if (is_array($waited)) {
                        return $filter ? $filter($waited) : $waited;
                    }
                }
            }
        }

        try {
            $resource = $this->query($query);
            $rows = $this->adapter->fetch($resource);
            if ($cacheKey) {
                $cache->set($cacheKey, $rows, $cacheTtl);
            }
        } finally {
            if ($cacheKey && $locked) {
                $cache->unlock($cacheKey);
            }
        }

        if (!$rows) {
            return null;
        }

        return $filter ? $filter($rows) : $rows;
    }

    public function fetchObject($query, ?callable $filter = null): ?\stdClass
    {
        $cache = Cache::getInstance();
        $cacheTtl = null;
        $cacheKey = $this->cacheKey($query, $cacheTtl, 'obj');
        $locked = false;
        if ($cacheKey) {
            $hit = false;
            $cached = $cache->get($cacheKey, $hit);
            if ($hit) {
                if ($cached === null) {
                    return null;
                }
                if ($cached instanceof \stdClass) {
                    return $filter ? $filter($cached) : $cached;
                }
            }

            $locked = $cache->tryLock($cacheKey, 6);
            if (!$locked) {
                $waited = null;
                if ($cache->waitFor($cacheKey, $waited, 6, 50000)) {
                    if ($waited === null) {
                        return null;
                    }
                    if ($waited instanceof \stdClass) {
                        return $filter ? $filter($waited) : $waited;
                    }
                }
            }
        }

        try {
            $resource = $this->query($query);
            $rows = $this->adapter->fetchObject($resource);
            if ($cacheKey) {
                $cache->set($cacheKey, $rows, $cacheTtl);
            }
        } finally {
            if ($cacheKey && $locked) {
                $cache->unlock($cacheKey);
            }
        }

        if (!$rows) {
            return null;
        }

        return $filter ? $filter($rows) : $rows;
    }
}
