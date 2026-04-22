<?php

namespace Utils\Migration;

use Typecho\Db;
use Utils\Schema;
use Utils\Migration\Steps\InstallMailAndResetInfrastructureStep;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class SchemaManager
{
    private const CRITICAL_TABLES = [
        'mail_queue' => [
            'label' => '邮件队列表',
            'columns' => ['id', 'type', 'status', 'attempts', 'lockedUntil', 'sendAt', 'created', 'updated', 'lastError', 'dedupeKey', 'payload'],
            'indexes' => ['idx_status_sendat', 'idx_status_updated', 'idx_locked', 'uniq_dedupe'],
            'mysql' => [
                'collationPrefix' => 'utf8mb4_',
                'columnTypes' => [
                    'id' => 'bigint unsigned',
                    'type' => 'varchar(16)',
                    'status' => 'varchar(16)',
                    'attempts' => 'int unsigned',
                    'lockedUntil' => 'int unsigned',
                    'sendAt' => 'int unsigned',
                    'created' => 'int unsigned',
                    'updated' => 'int unsigned',
                    'lastError' => 'varchar(500)',
                    'dedupeKey' => 'char(40)',
                    'payload' => 'longtext',
                ],
            ],
        ],
        'mail_unsub' => [
            'label' => '邮件退订表',
            'columns' => ['id', 'email', 'scope', 'created'],
            'indexes' => ['uniq_email_scope'],
            'mysql' => [
                'collationPrefix' => 'utf8mb4_',
                'columnTypes' => [
                    'id' => 'bigint unsigned',
                    'email' => 'varchar(255)',
                    'scope' => 'varchar(32)',
                    'created' => 'int unsigned',
                ],
            ],
        ],
        'password_resets' => [
            'label' => '密码重置表',
            'columns' => ['id', 'email', 'token', 'created', 'expires', 'used'],
            'indexes' => ['idx_email', 'idx_token', 'idx_expires'],
            'mysql' => [
                'collationPrefix' => 'utf8mb4_',
                'columnTypes' => [
                    'id' => 'bigint unsigned',
                    'email' => 'varchar(150)',
                    'token' => 'varchar(64)',
                    'created' => 'int unsigned',
                    'expires' => 'int unsigned',
                    'used' => 'tinyint unsigned',
                ],
            ],
        ],
    ];

    public static function inspectCriticalSchema(Db $db): array
    {
        $items = [];
        $missing = [];
        $prefix = (string) $db->getPrefix();
        $dialect = self::dialect($db);

        foreach (self::CRITICAL_TABLES as $key => $meta) {
            $exists = self::tableExists($db, 'table.' . $key);
            $missingColumns = $exists
                ? self::missingColumns($db, $prefix . $key, (array) ($meta['columns'] ?? []))
                : [];
            $missingIndexes = $exists
                ? self::missingIndexes($db, $prefix . $key, (array) ($meta['indexes'] ?? []))
                : [];
            $typeMismatches = ($exists && $dialect === 'mysql')
                ? self::mysqlTypeMismatches($db, $prefix . $key, (array) (($meta['mysql']['columnTypes'] ?? [])))
                : [];
            $tableCollation = ($exists && $dialect === 'mysql')
                ? self::mysqlTableCollation($db, $prefix . $key)
                : '';
            $collationPrefix = (string) ($meta['mysql']['collationPrefix'] ?? '');
            $collationOk = $tableCollation === '' || $collationPrefix === ''
                ? true
                : str_starts_with(strtolower($tableCollation), strtolower($collationPrefix));
            $schemaOk = $exists
                && $missingColumns === []
                && $missingIndexes === []
                && $typeMismatches === []
                && $collationOk;
            $item = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'table' => $prefix . $key,
                'exists' => $exists,
                'columnsOk' => $schemaOk,
                'missingColumns' => $missingColumns,
                'missingIndexes' => $missingIndexes,
                'typeMismatches' => $typeMismatches,
                'tableCollation' => $tableCollation,
                'collationOk' => $collationOk,
                'status' => !$exists
                    ? 'missing_table'
                    : (($missingColumns === [])
                        ? ($schemaOk ? 'ok' : 'schema_mismatch')
                        : 'missing_columns'),
            ];
            $items[] = $item;

            if (!$exists || !$schemaOk) {
                $missing[] = $item;
            }
        }

        return [
            'healthy' => empty($missing),
            'items' => $items,
            'missing' => $missing
        ];
    }

    public static function repairCriticalSchema(Db $db, Options $options): array
    {
        $before = self::inspectCriticalSchema($db);
        (new InstallMailAndResetInfrastructureStep())->up($db, $options);
        $after = self::inspectCriticalSchema($db);

        $repaired = [];
        foreach ($before['missing'] as $item) {
            foreach ($after['items'] as $afterItem) {
                if ($afterItem['key'] === $item['key'] && $afterItem['status'] === 'ok') {
                    $repaired[] = $afterItem;
                    break;
                }
            }
        }

        return [
            'healthy' => $after['healthy'],
            'after' => $after,
            'repaired' => $repaired
        ];
    }

    private static function tableExists(Db $db, string $tableAlias): bool
    {
        try {
            $db->fetchRow($db->select('1')->from($tableAlias)->limit(1));
            return true;
        } catch (\Typecho\Db\Adapter\SQLException $e) {
            return false;
        }
    }

    private static function missingColumns(Db $db, string $table, array $columns): array
    {
        $missing = [];

        foreach ($columns as $column) {
            if (!Schema::columnExists($db, $table, (string) $column)) {
                $missing[] = (string) $column;
            }
        }

        return $missing;
    }

    private static function missingIndexes(Db $db, string $table, array $indexes): array
    {
        $missing = [];

        foreach ($indexes as $index) {
            if (!self::indexExists($db, $table, (string) $index)) {
                $missing[] = (string) $index;
            }
        }

        return $missing;
    }

    private static function mysqlTypeMismatches(Db $db, string $table, array $columns): array
    {
        $mismatches = [];
        $columnMap = self::mysqlColumns($db, $table);

        foreach ($columns as $column => $expectedType) {
            $actual = strtolower((string) ($columnMap[$column]['Type'] ?? ''));
            if ($actual === '') {
                continue;
            }

            if (!str_contains($actual, strtolower((string) $expectedType))) {
                $mismatches[] = (string) $column;
            }
        }

        return $mismatches;
    }

    private static function mysqlColumns(Db $db, string $table): array
    {
        try {
            $rows = $db->fetchAll('SHOW FULL COLUMNS FROM ' . self::quoteMysql($table));
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '') {
                $map[$field] = $row;
            }
        }

        return $map;
    }

    private static function mysqlTableCollation(Db $db, string $table): string
    {
        try {
            $row = $db->fetchRow('SHOW TABLE STATUS LIKE ' . self::sqlString($table));
        } catch (\Throwable $e) {
            return '';
        }

        return (string) ($row['Collation'] ?? '');
    }

    private static function indexExists(Db $db, string $table, string $index): bool
    {
        return match (self::dialect($db)) {
            'sqlite' => self::sqliteIndexExists($db, $table, $index),
            'pgsql' => self::pgsqlIndexExists($db, $table, $index),
            default => self::mysqlIndexExists($db, $table, $index),
        };
    }

    private static function dialect(Db $db): string
    {
        $adapter = strtolower($db->getAdapterName());

        if (str_contains($adapter, 'sqlite')) {
            return 'sqlite';
        }

        if (str_contains($adapter, 'pgsql')) {
            return 'pgsql';
        }

        return 'mysql';
    }

    private static function mysqlIndexExists(Db $db, string $table, string $index): bool
    {
        try {
            $row = $db->fetchRow(
                'SHOW INDEX FROM ' . self::quoteMysql($table)
                . ' WHERE Key_name = ' . self::sqlString($index)
            );
        } catch (\Throwable $e) {
            return false;
        }

        return !empty($row);
    }

    private static function sqliteIndexExists(Db $db, string $table, string $index): bool
    {
        try {
            $rows = $db->fetchAll('PRAGMA index_list("' . str_replace('"', '""', $table) . '")');
        } catch (\Throwable $e) {
            return false;
        }

        foreach ($rows as $row) {
            if ((string) ($row['name'] ?? '') === $index) {
                return true;
            }
        }

        return false;
    }

    private static function pgsqlIndexExists(Db $db, string $table, string $index): bool
    {
        try {
            $row = $db->fetchRow(
                'SELECT 1 FROM pg_indexes'
                . ' WHERE schemaname = ANY (current_schemas(false))'
                . ' AND tablename = ' . self::sqlString($table)
                . ' AND indexname = ' . self::sqlString($index)
            );
        } catch (\Throwable $e) {
            return false;
        }

        return !empty($row);
    }

    private static function quoteMysql(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private static function sqlString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
