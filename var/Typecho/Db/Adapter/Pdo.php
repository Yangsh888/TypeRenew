<?php

namespace Typecho\Db\Adapter;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Adapter;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

abstract class Pdo implements Adapter
{
    protected \PDO $object;

    protected ?string $lastTable;

    public static function isAvailable(): bool
    {
        return class_exists('PDO');
    }

    /**
     * @throws ConnectionException
     */
    public function connect(Config $config): \PDO
    {
        try {
            $this->object = $this->init($config);
            $this->object->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $this->object;
        } catch (\PDOException $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode());
        }
    }

    abstract public function init(Config $config): \PDO;

    public function getVersion($handle): string
    {
        return $handle->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    /**
     * @throws SQLException
     */
    public function query(
        string $query,
        $handle,
        int $op = Db::READ,
        ?string $action = null,
        ?string $table = null
    ): \PDOStatement {
        try {
            $this->lastTable = $table;
            $resource = $handle->prepare($query);
            $resource->execute();
        } catch (\PDOException $e) {
            throw new SQLException($e->getMessage(), $e->getCode());
        }

        return $resource;
    }

    public function fetchAll($resource): array
    {
        return $resource->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetch($resource): ?array
    {
        return $resource->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function fetchObject($resource): ?\stdClass
    {
        return $resource->fetchObject() ?: null;
    }

    public function quoteValue($string): string
    {
        return $this->object->quote($string);
    }

    public function affectedRows($resource, $handle): int
    {
        return $resource->rowCount();
    }

    public function lastInsertId($resource, $handle): int
    {
        $lastInsertId = $handle->lastInsertId();
        return $lastInsertId === false ? 0 : (int) $lastInsertId;
    }
}
