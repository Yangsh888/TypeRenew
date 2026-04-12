<?php

namespace Typecho\Db\Adapter\Pdo;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Adapter\Pdo;
use Typecho\Db\Adapter\PgsqlTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Pgsql extends Pdo
{
    use PgsqlTrait;

    public static function isAvailable(): bool
    {
        return parent::isAvailable() && in_array('pgsql', \PDO::getAvailableDrivers());
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
        $this->prepareQuery($query, $handle, $action, $table);
        return parent::query($query, $handle, $op, $action, $table);
    }

    public function init(Config $config): \PDO
    {
        $dsn = "pgsql:dbname={$config->database};host={$config->host};port={$config->port}";

        if ($config->sslVerify) {
            $dsn .= ';sslmode=require';
        }

        $pdo = new \PDO(
            $dsn,
            $config->user,
            $config->password
        );

        if ($config->charset) {
            $pdo->exec("SET NAMES '{$config->charset}'");
        }

        return $pdo;
    }
}
