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

    /** 表内连接方式 */
    public const INNER_JOIN = 'INNER';

    /** 表外连接方式 */
    public const OUTER_JOIN = 'OUTER';

    /** 表左连接方式 */
    public const LEFT_JOIN = 'LEFT';

    /** 表右连接方式 */
    public const RIGHT_JOIN = 'RIGHT';

    /** 数据库查询操作 */
    public const SELECT = 'SELECT';

    /** 数据库更新操作 */
    public const UPDATE = 'UPDATE';

    /** 数据库插入操作 */
    public const INSERT = 'INSERT';

    /** 数据库删除操作 */
    public const DELETE = 'DELETE';

    /**
     * 数据库适配器
     * @var Adapter
     */
    private Adapter $adapter;

    /**
     * 默认配置
     *
     * @var array
     */
    private array $config;

    /**
     * 已经连接
     *
     * @var array
     */
    private array $connectedPool;

    /**
     * 前缀
     *
     * @var string
     */
    private string $prefix;

    /**
     * 适配器名称
     *
     * @var string
     */
    private string $adapterName;

    /**
     * 实例化的数据库对象
     * @var Db
     */
    private static Db $instance;

    /**
     * 数据库类构造函数
     *
     * @param string $adapterName 适配器名称
     * @param string $prefix 前缀
     *
     * @throws DbException
     */
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

    /**
     * @return Adapter
     */
    public function getAdapter(): Adapter
    {
        return $this->adapter;
    }

    /**
     * 获取适配器名称
     * @return string
     */
    public function getAdapterName(): string
    {
        return $this->adapterName;
    }

    /**
     * 获取表前缀
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @param Config $config
     * @param int $op
     */
    public function addConfig(Config $config, int $op): void
    {
        if ($op & self::READ) {
            $this->config[self::READ][] = $config;
        }

        if ($op & self::WRITE) {
            $this->config[self::WRITE][] = $config;
        }
    }

    /**
     * getConfig
     *
     * @param int $op
     *
     * @return Config
     * @throws DbException
     */
    public function getConfig(int $op): Config
    {
        if (empty($this->config[$op])) {
            throw new DbException('Missing Database Connection');
        }

        $key = array_rand($this->config[$op]);
        return $this->config[$op][$key];
    }

    /**
     * 重置连接池
     */
    public function flushPool(): void
    {
        $this->connectedPool = [];
    }

    /**
     * 选择数据库
     *
     * @param int $op
     *
     * @return mixed
     * @throws DbException
     */
    public function selectDb(int $op)
    {
        if (!isset($this->connectedPool[$op])) {
            $selectConnectionConfig = $this->getConfig($op);
            $selectConnectionHandle = $this->adapter->connect($selectConnectionConfig);
            $this->connectedPool[$op] = $selectConnectionHandle;
        }

        return $this->connectedPool[$op];
    }

    /**
     * 获取SQL词法构建器实例化对象
     *
     * @return Query
     */
    public function sql(): Query
    {
        return new Query($this->adapter, $this->prefix);
    }

    /**
     * 为多数据库提供支持
     *
     * @param array $config 数据库实例
     * @param int $op 数据库操作
     */
    public function addServer(array $config, int $op): void
    {
        $this->addConfig(Config::factory($config), $op);
        $this->flushPool();
    }

    /**
     * 获取版本
     *
     * @param int $op
     *
     * @return string
     * @throws DbException
     */
    public function getVersion(int $op = self::READ): string
    {
        return $this->adapter->getVersion($this->selectDb($op));
    }

    /**
     * 设置默认数据库对象
     *
     * @param Db $db 数据库对象
     */
    public static function set(Db $db): void
    {
        self::$instance = $db;
    }

    /**
     * 获取数据库实例化对象
     * 用静态变量存储实例化的数据库对象,可以保证数据连接仅进行一次
     *
     * @return Db
     * @throws DbException
     */
    public static function get(): Db
    {
        if (empty(self::$instance)) {
            throw new DbException('Missing Database Object');
        }

        return self::$instance;
    }

    /**
     * 选择查询字段
     *
     * @param ...$ags
     *
     * @return Query
     * @throws DbException
     */
    public function select(...$ags): Query
    {
        $this->selectDb(self::READ);

        $args = func_get_args();
        return call_user_func_array([$this->sql(), 'select'], $args ?: ['*']);
    }

    /**
     * 更新记录操作(UPDATE)
     *
     * @param string $table 需要更新记录的表
     *
     * @return Query
     * @throws DbException
     */
    public function update(string $table): Query
    {
        $this->selectDb(self::WRITE);

        return $this->sql()->update($table);
    }

    /**
     * 删除记录操作(DELETE)
     *
     * @param string $table 需要删除记录的表
     *
     * @return Query
     * @throws DbException
     */
    public function delete(string $table): Query
    {
        $this->selectDb(self::WRITE);

        return $this->sql()->delete($table);
    }

    /**
     * 插入记录操作(INSERT)
     *
     * @param string $table 需要插入记录的表
     *
     * @return Query
     * @throws DbException
     */
    public function insert(string $table): Query
    {
        $this->selectDb(self::WRITE);

        return $this->sql()->insert($table);
    }

    /**
     * @param string $table
     * @throws DbException
     */
    public function truncate(string $table): void
    {
        $table = preg_replace("/^table\./", $this->prefix, $table);
        $this->adapter->truncate($table, $this->selectDb(self::WRITE));
        Cache::getInstance()->invalidate($this->normalizeInvalidateTable($table));
    }

    /**
     * 执行查询语句
     *
     * @param mixed $query 查询语句或者查询对象
     * @param int $op 数据库读写状态
     * @param string $action 操作动作
     *
     * @return mixed
     * @throws DbException
     */
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

    /**
     * 一次取出所有行
     *
     * @param mixed $query 查询对象
     * @param callable|null $filter 行过滤器函数,将查询的每一行作为第一个参数传入指定的过滤器中
     *
     * @return array
     * @throws DbException
     */
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

    /**
     * 一次取出一行
     *
     * @param mixed $query 查询对象
     * @param callable|null $filter 行过滤器函数,将查询的每一行作为第一个参数传入指定的过滤器中
     * @return array|null
     * @throws DbException
     */
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

    /**
     * 一次取出一个对象
     *
     * @param mixed $query 查询对象
     * @param callable|null $filter 行过滤器函数,将查询的每一行作为第一个参数传入指定的过滤器中
     * @return \stdClass|null
     * @throws DbException
     */
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
