<?php

namespace Typecho\Db\Adapter;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Adapter;
use mysqli_sql_exception;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Mysqli implements Adapter
{
    use MysqlTrait;

    private \mysqli $dbLink;

    public static function isAvailable(): bool
    {
        return extension_loaded('mysqli');
    }

    /**
     * @throws ConnectionException
     */
    public function connect(Config $config): \mysqli
    {
        $mysqli = mysqli_init();
        if ($mysqli) {
            try {
                if (!empty($config->sslCa)) {
                    $mysqli->ssl_set(null, null, $config->sslCa, null, null);

                    if (isset($config->sslVerify)) {
                        $mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $config->sslVerify);
                    }
                }

                $host = (string) $config->host;
                $port = empty($config->port) ? null : $config->port;
                $socket = null;
                if (strpos($host, '/') !== false) {
                    $socket = $host;
                    $host = 'localhost';
                    $port = null;
                }

                $mysqli->real_connect(
                    $host,
                    $config->user,
                    $config->password,
                    $config->database,
                    $port,
                    $socket
                );

                $this->dbLink = $mysqli;

                if ($config->charset) {
                    $this->dbLink->set_charset($config->charset);
                    $collation = $this->resolveCollation((string) $config->charset, (string) $this->dbLink->server_info);
                    if ($collation !== null) {
                        $this->dbLink->query("SET NAMES '{$config->charset}' COLLATE '{$collation}'");
                    }
                }
            } catch (mysqli_sql_exception $e) {
                throw new ConnectionException($e->getMessage(), $e->getCode());
            }

            return $this->dbLink;
        }

        throw new ConnectionException("Couldn't connect to database.", mysqli_connect_errno());
    }

    public function getVersion($handle): string
    {
        return (string) $this->dbLink->server_info;
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
    ) {
        try {
            if ($resource = $this->dbLink->query($query)) {
                return $resource;
            }
        } catch (mysqli_sql_exception $e) {
            throw new SQLException($e->getMessage(), $e->getCode());
        }

        throw new SQLException($this->dbLink->error, $this->dbLink->errno);
    }

    public function quoteColumn(string $string): string
    {
        return '`' . $string . '`';
    }

    public function fetch($resource): ?array
    {
        return $resource->fetch_assoc() ?: null;
    }

    public function fetchAll($resource): array
    {
        if (method_exists($resource, 'fetch_all')) {
            return $resource->fetch_all(MYSQLI_ASSOC);
        }

        $rows = [];
        while ($row = $resource->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function fetchObject($resource): ?\stdClass
    {
        return $resource->fetch_object() ?: null;
    }

    public function quoteValue($string): string
    {
        return "'" . $this->dbLink->real_escape_string($string) . "'";
    }

    public function affectedRows($resource, $handle): int
    {
        return $handle->affected_rows;
    }

    public function lastInsertId($resource, $handle): int
    {
        return $handle->insert_id;
    }
}
