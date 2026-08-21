<?php

namespace Typecho\Db\Adapter\Pdo;

use Typecho\Config;
use Typecho\Db\Adapter\Pdo;
use Typecho\Db\Adapter\SQLiteTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class SQLite extends Pdo
{
    use SQLiteTrait;

    public static function isAvailable(): bool
    {
        return parent::isAvailable() && in_array('sqlite', \PDO::getAvailableDrivers());
    }

    public function init(Config $config): \PDO
    {
        return new \PDO("sqlite:{$config->file}");
    }

    public function fetchObject($resource): ?\stdClass
    {
        $result = $this->fetch($resource);
        return $result ? (object) $result : null;
    }

    public function fetch($resource): ?array
    {
        $result = parent::fetch($resource);
        return $result ? $this->filterColumnName($result) : null;
    }

    public function fetchAll($resource): array
    {
        return array_map([$this, 'filterColumnName'], parent::fetchAll($resource));
    }
}
