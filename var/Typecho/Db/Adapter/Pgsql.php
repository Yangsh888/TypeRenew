<?php

namespace Typecho\Db\Adapter;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Adapter;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Pgsql implements Adapter
{
    use PgsqlTrait;

    public static function isAvailable(): bool
    {
        return extension_loaded('pgsql');
    }

    /**
     * @throws ConnectionException
     */
    public function connect(Config $config)
    {
        $dsn = "host={$config->host} port={$config->port}"
            . " dbname={$config->database} user={$config->user} password={$config->password}";

        if ($config->sslVerify) {
            $dsn .= ' sslmode=require';
        }

        if ($config->charset) {
            $dsn .= " options='--client_encoding={$config->charset}'";
        }

        if ($dbLink = @pg_connect($dsn)) {
            return $dbLink;
        }

        throw new ConnectionException("Couldn't connect to database.");
    }

    public function getVersion($handle): string
    {
        $version = pg_version($handle);
        return $version['server'];
    }

    /**
     * @throws SQLException
     */
    public function query(string $query, $handle, int $op = Db::READ, ?string $action = null, ?string $table = null)
    {
        $this->prepareQuery($query, $handle, $action, $table);
        if ($resource = pg_query($handle, $query)) {
            return $resource;
        }

        throw new SQLException(
            @pg_last_error($handle),
            pg_result_error_field(pg_get_result($handle), PGSQL_DIAG_SQLSTATE)
        );
    }

    public function fetch($resource): ?array
    {
        return pg_fetch_assoc($resource) ?: null;
    }

    public function fetchObject($resource): ?\stdClass
    {
        return pg_fetch_object($resource) ?: null;
    }

    public function fetchAll($resource): array
    {
        return pg_fetch_all($resource, PGSQL_ASSOC) ?: [];
    }

    public function affectedRows($resource, $handle): int
    {
        return pg_affected_rows($resource);
    }

    public function quoteValue($string): string
    {
        return '\'' . str_replace('\'', '\'\'', $string) . '\'';
    }
}
