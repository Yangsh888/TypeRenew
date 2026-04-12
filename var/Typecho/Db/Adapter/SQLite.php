<?php

namespace Typecho\Db\Adapter;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Adapter;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class SQLite implements Adapter
{
    use SQLiteTrait;

    public static function isAvailable(): bool
    {
        return extension_loaded('sqlite3');
    }

    /**
     * @throws ConnectionException
     */
    public function connect(Config $config): \SQLite3
    {
        try {
            $dbHandle = new \SQLite3($config->file);
            $this->isSQLite2 = version_compare(\SQLite3::version()['versionString'], '3.0.0', '<');
        } catch (\Exception $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode());
        }

        return $dbHandle;
    }

    public function getVersion($handle): string
    {
        return \SQLite3::version()['versionString'];
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
    ): \SQLite3Result {
        if ($stm = $handle->prepare($query)) {
            if ($resource = $stm->execute()) {
                return $resource;
            }
        }

        throw new SQLException($handle->lastErrorMsg(), $handle->lastErrorCode());
    }

    public function fetchObject($resource): ?\stdClass
    {
        $result = $this->fetch($resource);
        return $result ? (object) $result : null;
    }

    public function fetch($resource): ?array
    {
        $result = $resource->fetchArray(SQLITE3_ASSOC);
        return $result ? $this->filterColumnName($result) : null;
    }

    public function fetchAll($resource): array
    {
        $result = [];

        while ($row = $this->fetch($resource)) {
            $result[] = $row;
        }

        return $result;
    }

    public function quoteValue($string): string
    {
        return '\'' . str_replace('\'', '\'\'', $string) . '\'';
    }

    public function affectedRows($resource, $handle): int
    {
        return $handle->changes();
    }

    public function lastInsertId($resource, $handle): int
    {
        return $handle->lastInsertRowID();
    }
}
