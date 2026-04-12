<?php

namespace Typecho\Db\Adapter\Pdo;

use Typecho\Config;
use Typecho\Db\Adapter\MysqlTrait;
use Typecho\Db\Adapter\Pdo;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Mysql extends Pdo
{
    use MysqlTrait;

    public static function isAvailable(): bool
    {
        return parent::isAvailable() && in_array('mysql', \PDO::getAvailableDrivers());
    }

    public function quoteColumn(string $string): string
    {
        return '`' . $string . '`';
    }

    public function init(Config $config): \PDO
    {
        $options = [];
        if (!empty($config->sslCa)) {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = $config->sslCa;

            if (isset($config->sslVerify)) {
                $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $config->sslVerify;
            }
        }

        $dsn = !empty($config->dsn)
            ? $config->dsn
            : (strpos((string) $config->host, '/') !== false
                ? "mysql:dbname={$config->database};unix_socket={$config->host}"
                : "mysql:dbname={$config->database};host={$config->host};port={$config->port}");

        $pdo = new \PDO(
            $dsn,
            $config->user,
            $config->password,
            $options
        );

        if ($config->charset) {
            $collation = $this->resolveCollation((string) $config->charset);
            $sql = "SET NAMES '{$config->charset}'";
            if ($collation !== null) {
                $sql .= " COLLATE '{$collation}'";
            }
            $pdo->exec($sql);
        }

        if (class_exists('\Pdo\Mysql')) {
            $pdo->setAttribute(\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY, true);
        } else {
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }

        return $pdo;
    }

    public function quoteValue($string): string
    {
        return parent::quoteValue($string);
    }
}
