<?php

namespace Typecho\Db;

use Typecho\Config;
use Typecho\Db;

/**
 * Typecho数据库适配器
 * 定义通用的数据库适配接口
 *
 * @package Db
 */
interface Adapter
{
    public static function isAvailable(): bool;

    public function connect(Config $config);

    public function getVersion($handle): string;

    public function getDriver(): string;

    public function truncate(string $table, $handle);

    public function query(string $query, $handle, int $op = Db::READ, ?string $action = null, ?string $table = null);

    public function fetch($resource): ?array;

    public function fetchAll($resource): array;

    public function fetchObject($resource): ?\stdClass;

    public function quoteValue($string): string;

    public function quoteColumn(string $string): string;

    public function parseSelect(array $sql): string;

    public function affectedRows($resource, $handle): int;

    public function lastInsertId($resource, $handle): int;
}
