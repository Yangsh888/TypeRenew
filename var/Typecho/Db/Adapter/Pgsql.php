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

    public function connect(Config $config)
    {
        $quote = static fn($value): string => "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value) . "'";

        $dsn = 'host=' . $quote($config->host) . ' port=' . $quote($config->port)
            . ' dbname=' . $quote($config->database) . ' user=' . $quote($config->user)
            . ' password=' . $quote($config->password);

        if ($config->sslVerify) {
            $dsn .= ' sslmode=verify-full';
        }

        if ($config->charset) {
            $dsn .= " options='--client_encoding=" . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $config->charset) . "'";
        }

        $dbLink = pg_connect($dsn);
        if ($dbLink) {
            return $dbLink;
        }

        throw new ConnectionException("Couldn't connect to database.");
    }

    public function getVersion($handle): string
    {
        $version = pg_version($handle);
        return $version['server'];
    }

    public function query(string $query, $handle, int $op = Db::READ, ?string $action = null, ?string $table = null)
    {
        $this->prepareQuery($query, $handle, $action, $table);
        if ($resource = pg_query($handle, $query)) {
            return $resource;
        }

        throw new SQLException((string) pg_last_error($handle), 0);
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
