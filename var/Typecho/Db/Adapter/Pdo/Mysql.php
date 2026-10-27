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
        $attr = static fn(string $name): int => constant(
            class_exists(\Pdo\Mysql::class, false) ? \Pdo\Mysql::class . '::ATTR_' . $name : 'PDO::MYSQL_ATTR_' . $name
        );
        $options = [];
        if (!empty($config->sslCa)) {
            $options[$attr('SSL_CA')] = $config->sslCa;

            if (isset($config->sslVerify)) {
                $options[$attr('SSL_VERIFY_SERVER_CERT')] = $config->sslVerify;
            }
        }

        if (!empty($config->dsn)) {
            $dsn = (string) $config->dsn;
            if (!str_starts_with($dsn, 'mysql:')) {
                throw new \Typecho\Db\Adapter\ConnectionException('Invalid PDO DSN');
            }
        } elseif (strpos((string) $config->host, '/') !== false) {
            $dsn = "mysql:dbname={$config->database};unix_socket={$config->host}";
        } else {
            $dsn = "mysql:dbname={$config->database};host={$config->host};port={$config->port}";
        }

        $pdo = new \PDO(
            $dsn,
            $config->user,
            $config->password,
            $options
        );

        if ($config->charset) {
            $charset = (string) $config->charset;
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $charset)) {
                throw new \Typecho\Db\Adapter\ConnectionException('Invalid charset identifier');
            }
            $collation = $this->resolveCollation(
                $charset,
                (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION)
            );
            $sql = "SET NAMES '{$charset}'";
            if ($collation !== null && preg_match('/^[A-Za-z0-9_-]+$/', $collation)) {
                $sql .= " COLLATE '{$collation}'";
            }
            $pdo->exec($sql);
        }

        $pdo->setAttribute($attr('USE_BUFFERED_QUERY'), true);

        return $pdo;
    }

}
