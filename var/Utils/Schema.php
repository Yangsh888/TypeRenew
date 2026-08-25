<?php

namespace Utils;

use Typecho\Db;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Schema
{
    public static function ensureMailInfra(Db $db): void
    {
        self::ensureTables($db, ['mail_queue', 'mail_unsub', 'password_resets']);
    }

    public static function ensureAuthInfra(Db $db): void
    {
        self::ensureTables($db, ['login_attempts']);
    }

    public static function repairAuthInfra(Db $db): void
    {
        if (self::dialect($db) !== 'mysql') {
            return;
        }

        $expectedCollation = self::detectMysqlCollation($db);
        $table = $db->getPrefix() . 'login_attempts';
        self::ensureMysqlTableCollation($db, $table, $expectedCollation);
        self::ensureMysqlColumnDefinitions($db, 'login_attempts', $table);
    }

    public static function criticalSchema(): array
    {
        return [
            'mail_queue' => [
                'label' => _t('邮件队列表'),
                'mysql' => [
                    'definitions' => [
                        'id' => '`id` bigint unsigned NOT NULL auto_increment',
                        'type' => '`type` varchar(16) NOT NULL',
                        'status' => '`status` varchar(16) NOT NULL',
                        'attempts' => '`attempts` int unsigned NOT NULL default 0',
                        'lockedUntil' => '`lockedUntil` int unsigned NOT NULL default 0',
                        'sendAt' => '`sendAt` int unsigned NOT NULL default 0',
                        'created' => '`created` int unsigned NOT NULL default 0',
                        'updated' => '`updated` int unsigned NOT NULL default 0',
                        'lastError' => "`lastError` varchar(500) NOT NULL default ''",
                        'dedupeKey' => "`dedupeKey` char(40) NOT NULL default ''",
                        'payload' => '`payload` longtext',
                    ],
                ],
            ],
            'mail_unsub' => [
                'label' => _t('邮件退订表'),
                'mysql' => [
                    'definitions' => [
                        'id' => '`id` bigint unsigned NOT NULL auto_increment',
                        'email' => '`email` varchar(255) NOT NULL',
                        'scope' => '`scope` varchar(32) NOT NULL',
                        'created' => '`created` int unsigned NOT NULL default 0',
                    ],
                ],
            ],
            'password_resets' => [
                'label' => _t('密码重置表'),
                'mysql' => [
                    'definitions' => [
                        'id' => '`id` bigint unsigned NOT NULL auto_increment',
                        'email' => '`email` varchar(150) NOT NULL',
                        'token' => '`token` varchar(64) NOT NULL',
                        'created' => '`created` int unsigned NOT NULL default 0',
                        'expires' => '`expires` int unsigned NOT NULL default 0',
                        'used' => '`used` tinyint unsigned NOT NULL default 0',
                    ],
                ],
            ],
            'login_attempts' => [
                'label' => _t('登录限流表'),
                'mysql' => [
                    'definitions' => [
                        'id' => '`id` bigint unsigned NOT NULL auto_increment',
                        'scope' => "`scope` varchar(16) NOT NULL default ''",
                        'ipHash' => "`ipHash` char(40) NOT NULL default ''",
                        'identityHash' => "`identityHash` char(40) NOT NULL default ''",
                        'failures' => '`failures` int unsigned NOT NULL default 0',
                        'firstAt' => '`firstAt` int unsigned NOT NULL default 0',
                        'lastAt' => '`lastAt` int unsigned NOT NULL default 0',
                        'lockedUntil' => '`lockedUntil` int unsigned NOT NULL default 0',
                    ],
                ],
            ],
        ];
    }

    public static function criticalColumns(Db $db, string $tableKey): array
    {
        return array_keys(self::columnDefinitions(self::dialect($db), $tableKey));
    }

    public static function criticalIndexes(Db $db, string $tableKey, string $table): array
    {
        return array_map(
            static fn(array $index): string => (string) ($index['name'] ?? ''),
            self::tableIndexes(self::dialect($db), $tableKey, $table)
        );
    }

    public static function ensureCoreIndexes(Db $db): void
    {
        $prefix = $db->getPrefix();

        self::ensureIndex($db, $prefix . 'comments', $prefix . 'comments_status', ['status']);
        self::ensureIndex($db, $prefix . 'comments', $prefix . 'comments_author', ['authorId']);
        self::ensureIndex($db, $prefix . 'comments', $prefix . 'comments_cid_status', ['cid', 'status']);
        self::ensureIndex($db, $prefix . 'comments', $prefix . 'comments_owner_status', ['ownerId', 'status']);
        self::ensureIndex($db, $prefix . 'comments', $prefix . 'comments_parent', ['parent']);

        self::ensureIndex($db, $prefix . 'contents', $prefix . 'contents_type_status_created', ['type', 'status', 'created']);
        self::ensureIndex($db, $prefix . 'contents', $prefix . 'contents_author_type_status_created', ['authorId', 'type', 'status', 'created']);
        self::ensureIndex($db, $prefix . 'contents', $prefix . 'contents_parent_type', ['parent', 'type']);

        self::ensureIndex($db, $prefix . 'metas', $prefix . 'metas_type_slug', ['type', 'slug']);
        self::ensureIndex($db, $prefix . 'metas', $prefix . 'metas_type_order', ['type', 'order']);
        self::ensureIndex($db, $prefix . 'metas', $prefix . 'metas_type_parent_order', ['type', 'parent', 'order']);
        self::ensureIndex($db, $prefix . 'relationships', $prefix . 'relationships_mid', ['mid']);
    }

    public static function repairMailInfra(Db $db): void
    {
        if (self::dialect($db) !== 'mysql') {
            return;
        }

        $expectedCollation = self::detectMysqlCollation($db);
        foreach (['mail_queue', 'mail_unsub', 'password_resets'] as $tableKey) {
            $table = $db->getPrefix() . $tableKey;
            self::ensureMysqlTableCollation($db, $table, $expectedCollation);
            self::ensureMysqlColumnDefinitions($db, $tableKey, $table);
        }
    }

    public static function detectMysqlCollation(Db $db): string
    {
        return self::mysqlCollation($db, self::dialect($db));
    }

    public static function ensureUserPasswordStorage(Db $db): void
    {
        $dialect = self::dialect($db);
        $table = $db->getPrefix() . 'users';

        if ($dialect === 'sqlite') {
            return;
        }

        if ($dialect === 'pgsql') {
            $row = $db->fetchRow(
                $db->select('character_maximum_length')->from('information_schema.columns')
                    ->where('table_name = ? AND column_name = ?', $table, 'password')
                    ->limit(1)
            );

            if ((int) ($row['character_maximum_length'] ?? 0) === 255) {
                return;
            }

            $db->query(
                'ALTER TABLE ' . self::quote($table, $dialect)
                . ' ALTER COLUMN "password" TYPE VARCHAR(255)',
                Db::WRITE
            );
            return;
        }

        $columns = self::mysqlColumns($db, $table);
        $passwordType = strtolower((string) ($columns['password']['Type'] ?? ''));
        if ($passwordType === 'varchar(255)') {
            return;
        }

        $db->query(
            'ALTER TABLE ' . self::quote($table, $dialect)
            . ' MODIFY COLUMN `password` varchar(255) DEFAULT NULL',
            Db::WRITE
        );
    }

    public static function ensureTables(Db $db, array $tables): void
    {
        $dialect = self::dialect($db);

        foreach ($tables as $tableKey) {
            $table = $db->getPrefix() . $tableKey;
            $sql = self::tableSql($db, $dialect, $tableKey, $table);
            if ($sql === '') {
                continue;
            }

            $db->query($sql, Db::WRITE);
            self::ensureColumns($db, $dialect, $tableKey, $table);

            foreach (self::tableIndexes($dialect, $tableKey, $table) as $index) {
                self::ensureIndex(
                    $db,
                    $table,
                    $index['name'],
                    $index['columns'],
                    $index['unique'] ?? false
                );
            }
        }
    }

    private static function ensureColumns(Db $db, string $dialect, string $tableKey, string $table): void
    {
        $added = [];

        foreach (self::columnDefinitions($dialect, $tableKey) as $column => $definition) {
            if (self::columnExists($db, $table, $column)) {
                continue;
            }

            if ($dialect === 'sqlite' && $column === 'id') {
                throw new \RuntimeException(_t('%s 缺少主键列 id，SQLite 需要重建该表', $table));
            }

            $db->query(
                'ALTER TABLE ' . self::quote($table, $dialect)
                . ' ADD COLUMN ' . $definition,
                Db::WRITE
            );
            $added[] = $column;
        }

        if ($tableKey === 'mail_queue' && (in_array('dedupeKey', $added, true) || self::indexNeedsBackfill($db, $table, 'dedupeKey'))) {
            self::backfillMailDedupeKeys($db);
        }
    }

    private static function indexNeedsBackfill(Db $db, string $table, string $column): bool
    {
        try {
            $row = $db->fetchRow($db->select('COUNT(*) AS count')->from($table)->where($column . ' = ?', ''));
            return (int) ($row['count'] ?? 0) > 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function backfillMailDedupeKeys(Db $db): void
    {
        do {
            $rows = $db->fetchAll(
                $db->select('id')->from('table.mail_queue')
                    ->where('dedupeKey = ?', '')
                    ->order('id', Db::SORT_ASC)
                    ->limit(500)
            );

            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $db->query(
                    $db->update('table.mail_queue')
                        ->rows(['dedupeKey' => sha1('legacy:' . $id)])
                        ->where('id = ? AND dedupeKey = ?', $id, '')
                );
            }
        } while (count($rows) === 500);
    }

    private static function columnDefinitions(string $dialect, string $tableKey): array
    {
        if ($dialect === 'mysql') {
            $definitions = (array) (self::criticalSchema()[$tableKey]['mysql']['definitions'] ?? []);

            if (isset($definitions['id'])) {
                $definitions['id'] .= ' PRIMARY KEY';
            }

            return $definitions;
        }

        $definitions = [
            'mail_queue' => [
                'id' => $dialect === 'pgsql' ? '"id" BIGSERIAL PRIMARY KEY' : '"id" INTEGER PRIMARY KEY AUTOINCREMENT',
                'type' => '"type" VARCHAR(16) NOT NULL DEFAULT \'\'',
                'status' => '"status" VARCHAR(16) NOT NULL DEFAULT \'\'',
                'attempts' => '"attempts" INT NOT NULL DEFAULT 0',
                'lockedUntil' => '"lockedUntil" INT NOT NULL DEFAULT 0',
                'sendAt' => '"sendAt" INT NOT NULL DEFAULT 0',
                'created' => '"created" INT NOT NULL DEFAULT 0',
                'updated' => '"updated" INT NOT NULL DEFAULT 0',
                'lastError' => '"lastError" VARCHAR(500) NOT NULL DEFAULT \'\'',
                'dedupeKey' => '"dedupeKey" VARCHAR(40) NOT NULL DEFAULT \'\'',
                'payload' => '"payload" TEXT NULL',
            ],
            'mail_unsub' => [
                'id' => $dialect === 'pgsql' ? '"id" BIGSERIAL PRIMARY KEY' : '"id" INTEGER PRIMARY KEY AUTOINCREMENT',
                'email' => '"email" VARCHAR(255) NOT NULL DEFAULT \'\'',
                'scope' => '"scope" VARCHAR(32) NOT NULL DEFAULT \'\'',
                'created' => '"created" INT NOT NULL DEFAULT 0',
            ],
            'password_resets' => [
                'id' => $dialect === 'pgsql' ? '"id" BIGSERIAL PRIMARY KEY' : '"id" INTEGER PRIMARY KEY AUTOINCREMENT',
                'email' => '"email" VARCHAR(150) NOT NULL DEFAULT \'\'',
                'token' => '"token" VARCHAR(64) NOT NULL DEFAULT \'\'',
                'created' => '"created" INT NOT NULL DEFAULT 0',
                'expires' => '"expires" INT NOT NULL DEFAULT 0',
                'used' => '"used" INT NOT NULL DEFAULT 0',
            ],
            'login_attempts' => [
                'id' => $dialect === 'pgsql' ? '"id" BIGSERIAL PRIMARY KEY' : '"id" INTEGER PRIMARY KEY AUTOINCREMENT',
                'scope' => '"scope" VARCHAR(16) NOT NULL DEFAULT \'\'',
                'ipHash' => '"ipHash" VARCHAR(40) NOT NULL DEFAULT \'\'',
                'identityHash' => '"identityHash" VARCHAR(40) NOT NULL DEFAULT \'\'',
                'failures' => '"failures" INT NOT NULL DEFAULT 0',
                'firstAt' => '"firstAt" INT NOT NULL DEFAULT 0',
                'lastAt' => '"lastAt" INT NOT NULL DEFAULT 0',
                'lockedUntil' => '"lockedUntil" INT NOT NULL DEFAULT 0',
            ],
        ];

        return $definitions[$tableKey] ?? [];
    }

    public static function dialect(Db $db): string
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

    private static function tableSql(Db $db, string $dialect, string $tableKey, string $table): string
    {
        $name = self::quote($table, $dialect);
        $mysqlCollation = self::mysqlCollation($db, $dialect);

        switch ($tableKey) {
            case 'mail_queue':
                return match ($dialect) {
                    'sqlite' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                        . '"type" varchar(16) NOT NULL,'
                        . '"status" varchar(16) NOT NULL,'
                        . '"attempts" int(10) NOT NULL default 0,'
                        . '"lockedUntil" int(10) NOT NULL default 0,'
                        . '"sendAt" int(10) NOT NULL default 0,'
                        . '"created" int(10) NOT NULL default 0,'
                        . '"updated" int(10) NOT NULL default 0,'
                        . '"lastError" varchar(500) NOT NULL default "",'
                        . '"dedupeKey" varchar(40) NOT NULL default "",'
                        . '"payload" text'
                        . ')',
                    'pgsql' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" BIGSERIAL PRIMARY KEY,'
                        . '"type" VARCHAR(16) NOT NULL,'
                        . '"status" VARCHAR(16) NOT NULL,'
                        . '"attempts" INT NOT NULL DEFAULT 0,'
                        . '"lockedUntil" INT NOT NULL DEFAULT 0,'
                        . '"sendAt" INT NOT NULL DEFAULT 0,'
                        . '"created" INT NOT NULL DEFAULT 0,'
                        . '"updated" INT NOT NULL DEFAULT 0,'
                        . '"lastError" VARCHAR(500) NOT NULL DEFAULT \'\','
                        . '"dedupeKey" VARCHAR(40) NOT NULL DEFAULT \'\','
                        . '"payload" TEXT NULL'
                        . ')',
                    default => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '`id` bigint unsigned NOT NULL auto_increment,'
                        . '`type` varchar(16) NOT NULL,'
                        . '`status` varchar(16) NOT NULL,'
                        . '`attempts` int unsigned NOT NULL default 0,'
                        . '`lockedUntil` int unsigned NOT NULL default 0,'
                        . '`sendAt` int unsigned NOT NULL default 0,'
                        . '`created` int unsigned NOT NULL default 0,'
                        . '`updated` int unsigned NOT NULL default 0,'
                        . '`lastError` varchar(500) NOT NULL default \'\','
                        . '`dedupeKey` char(40) NOT NULL default \'\','
                        . '`payload` longtext,'
                        . 'PRIMARY KEY (`id`)'
                        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=' . $mysqlCollation,
                };

            case 'mail_unsub':
                return match ($dialect) {
                    'sqlite' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                        . '"email" varchar(255) NOT NULL,'
                        . '"scope" varchar(32) NOT NULL,'
                        . '"created" int(10) NOT NULL default 0'
                        . ')',
                    'pgsql' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" BIGSERIAL PRIMARY KEY,'
                        . '"email" VARCHAR(255) NOT NULL,'
                        . '"scope" VARCHAR(32) NOT NULL,'
                        . '"created" INT NOT NULL DEFAULT 0'
                        . ')',
                    default => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '`id` bigint unsigned NOT NULL auto_increment,'
                        . '`email` varchar(255) NOT NULL,'
                        . '`scope` varchar(32) NOT NULL,'
                        . '`created` int unsigned NOT NULL default 0,'
                        . 'PRIMARY KEY (`id`)'
                        . ') ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=' . $mysqlCollation,
                };

            case 'password_resets':
                return match ($dialect) {
                    'sqlite' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                        . '"email" varchar(150) NOT NULL,'
                        . '"token" varchar(64) NOT NULL,'
                        . '"created" int(10) NOT NULL default 0,'
                        . '"expires" int(10) NOT NULL default 0,'
                        . '"used" int(10) NOT NULL default 0'
                        . ')',
                    'pgsql' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" BIGSERIAL PRIMARY KEY,'
                        . '"email" VARCHAR(150) NOT NULL,'
                        . '"token" VARCHAR(64) NOT NULL,'
                        . '"created" INT NOT NULL DEFAULT 0,'
                        . '"expires" INT NOT NULL DEFAULT 0,'
                        . '"used" INT NOT NULL DEFAULT 0'
                        . ')',
                    default => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '`id` bigint unsigned NOT NULL auto_increment,'
                        . '`email` varchar(150) NOT NULL,'
                        . '`token` varchar(64) NOT NULL,'
                        . '`created` int unsigned NOT NULL default 0,'
                        . '`expires` int unsigned NOT NULL default 0,'
                        . '`used` tinyint unsigned NOT NULL default 0,'
                        . 'PRIMARY KEY (`id`)'
                        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=' . $mysqlCollation,
                };

            case 'login_attempts':
                return match ($dialect) {
                    'sqlite' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                        . '"scope" varchar(16) NOT NULL default \'\','
                        . '"ipHash" varchar(40) NOT NULL default \'\','
                        . '"identityHash" varchar(40) NOT NULL default \'\','
                        . '"failures" int(10) NOT NULL default 0,'
                        . '"firstAt" int(10) NOT NULL default 0,'
                        . '"lastAt" int(10) NOT NULL default 0,'
                        . '"lockedUntil" int(10) NOT NULL default 0'
                        . ')',
                    'pgsql' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" BIGSERIAL PRIMARY KEY,'
                        . '"scope" VARCHAR(16) NOT NULL DEFAULT \'\','
                        . '"ipHash" VARCHAR(40) NOT NULL DEFAULT \'\','
                        . '"identityHash" VARCHAR(40) NOT NULL DEFAULT \'\','
                        . '"failures" INT NOT NULL DEFAULT 0,'
                        . '"firstAt" INT NOT NULL DEFAULT 0,'
                        . '"lastAt" INT NOT NULL DEFAULT 0,'
                        . '"lockedUntil" INT NOT NULL DEFAULT 0'
                        . ')',
                    default => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '`id` bigint unsigned NOT NULL auto_increment,'
                        . '`scope` varchar(16) NOT NULL default \'\','
                        . '`ipHash` char(40) NOT NULL default \'\','
                        . '`identityHash` char(40) NOT NULL default \'\','
                        . '`failures` int unsigned NOT NULL default 0,'
                        . '`firstAt` int unsigned NOT NULL default 0,'
                        . '`lastAt` int unsigned NOT NULL default 0,'
                        . '`lockedUntil` int unsigned NOT NULL default 0,'
                        . 'PRIMARY KEY (`id`),'
                        . 'UNIQUE KEY `uniq_scope_ip_identity` (`scope`, `ipHash`, `identityHash`)'
                        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=' . $mysqlCollation,
                };
        }

        return '';
    }

    private static function tableIndexes(string $dialect, string $tableKey, string $table): array
    {
        $name = static function (string $mysqlName, string $otherSuffix) use ($dialect, $table): string {
            return $dialect === 'mysql' ? $mysqlName : $table . '_' . $otherSuffix;
        };

        return match ($tableKey) {
            'mail_queue' => [
                ['name' => $name('idx_status_sendat', 'status_sendat'), 'columns' => ['status', 'sendAt']],
                ['name' => $name('idx_status_updated', 'status_updated'), 'columns' => ['status', 'updated']],
                ['name' => $name('idx_locked', 'locked'), 'columns' => ['lockedUntil']],
                ['name' => $name('uniq_dedupe', 'dedupeKey'), 'columns' => ['dedupeKey'], 'unique' => true],
            ],
            'mail_unsub' => [
                ['name' => $name('uniq_email_scope', 'email_scope'), 'columns' => ['email', 'scope'], 'unique' => true],
            ],
            'password_resets' => [
                ['name' => $name('idx_email', 'email'), 'columns' => ['email']],
                ['name' => $name('idx_token', 'token'), 'columns' => ['token']],
                ['name' => $name('idx_expires', 'expires'), 'columns' => ['expires']],
            ],
            'login_attempts' => [
                [
                    'name' => $name('uniq_scope_ip_identity', 'scope_ip_identity'),
                    'columns' => ['scope', 'ipHash', 'identityHash'],
                    'unique' => true
                ],
                ['name' => $name('idx_locked', 'locked'), 'columns' => ['lockedUntil']],
                ['name' => $name('idx_last', 'last'), 'columns' => ['lastAt']],
            ],
            default => [],
        };
    }

    public static function ensureIndex(Db $db, string $table, string $index, array $columns, bool $unique = false): void
    {
        if (self::indexExists($db, $table, $index)) {
            return;
        }

        $dialect = self::dialect($db);
        $quotedColumns = array_map(static fn(string $column): string => self::quote($column, $dialect), $columns);
        $sql = 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX '
            . self::quote($index, $dialect)
            . ' ON ' . self::quote($table, $dialect)
            . ' (' . implode(', ', $quotedColumns) . ')';

        $db->query($sql, Db::WRITE);
    }

    public static function columnExists(Db $db, string $table, string $column): bool
    {
        $dialect = self::dialect($db);

        return match ($dialect) {
            'sqlite' => self::sqliteColumnExists($db, $table, $column),
            'pgsql' => self::pgsqlColumnExists($db, $table, $column),
            default => self::mysqlColumnExists($db, $table, $column),
        };
    }

    public static function indexExists(Db $db, string $table, string $index): bool
    {
        $dialect = self::dialect($db);

        return match ($dialect) {
            'sqlite' => self::sqliteIndexExists($db, $table, $index),
            'pgsql' => self::pgsqlIndexExists($db, $table, $index),
            default => self::mysqlIndexExists($db, $table, $index),
        };
    }

    public static function mysqlColumns(Db $db, string $table): array
    {
        try {
            $rows = $db->fetchAll('SHOW FULL COLUMNS FROM ' . self::quote($table, 'mysql'));
        } catch (\Throwable) {
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

    public static function mysqlTypeMismatches(Db $db, string $table, array $definitions): array
    {
        $mismatches = [];
        $columnMap = self::mysqlColumns($db, $table);

        foreach ($definitions as $column => $definition) {
            $actual = self::normalizeMysqlType((string) ($columnMap[$column]['Type'] ?? ''));
            $expectedType = self::mysqlDefinitionType((string) $definition);
            if ($actual === '') {
                continue;
            }

            if ($expectedType !== '' && !str_contains($actual, $expectedType)) {
                $mismatches[] = (string) $column;
            }
        }

        return $mismatches;
    }

    private static function mysqlIndexExists(Db $db, string $table, string $index): bool
    {
        try {
            $row = $db->fetchRow(
                'SHOW INDEX FROM ' . self::quote($table, 'mysql')
                . ' WHERE Key_name = ' . self::sqlString($index)
            );

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function mysqlColumnExists(Db $db, string $table, string $column): bool
    {
        try {
            $row = $db->fetchRow(
                'SHOW COLUMNS FROM ' . self::quote($table, 'mysql')
                . ' LIKE ' . self::sqlString($column)
            );

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function sqliteIndexExists(Db $db, string $table, string $index): bool
    {
        try {
            $row = $db->fetchRow(
                'SELECT 1 FROM sqlite_master'
                . ' WHERE type = \'index\''
                . ' AND tbl_name = ' . self::sqlString($table)
                . ' AND name = ' . self::sqlString($index)
                . ' LIMIT 1'
            );

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function sqliteColumnExists(Db $db, string $table, string $column): bool
    {
        try {
            $rows = $db->fetchAll('PRAGMA table_info(' . self::quote($table, 'sqlite') . ')');
            foreach ($rows as $row) {
                if (($row['name'] ?? null) === $column) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function pgsqlIndexExists(Db $db, string $table, string $index): bool
    {
        try {
            $row = $db->fetchRow(
                'SELECT 1 FROM pg_indexes'
                . ' WHERE schemaname = ANY (current_schemas(false))'
                . ' AND tablename = ' . self::sqlString($table)
                . ' AND indexname = ' . self::sqlString($index)
                . ' LIMIT 1'
            );

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function pgsqlColumnExists(Db $db, string $table, string $column): bool
    {
        try {
            $row = $db->fetchRow(
                'SELECT 1 FROM information_schema.columns'
                . ' WHERE table_schema = ANY (current_schemas(false))'
                . ' AND table_name = ' . self::sqlString($table)
                . ' AND column_name = ' . self::sqlString($column)
                . ' LIMIT 1'
            );

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function quote(string $name, string $dialect): string
    {
        $escaped = str_replace($dialect === 'mysql' ? '`' : '"', $dialect === 'mysql' ? '``' : '""', $name);

        return $dialect === 'mysql' ? '`' . $escaped . '`' : '"' . $escaped . '"';
    }

    private static function sqlString(string $value): string
    {
        return '\'' . str_replace('\'', '\'\'', $value) . '\'';
    }

    public static function mysqlTableStatus(Db $db, string $table): array
    {
        try {
            $row = $db->fetchRow(
                'SHOW TABLE STATUS WHERE Name = ' . self::sqlString($table)
            );
        } catch (\Throwable) {
            return [];
        }

        return is_array($row) ? $row : [];
    }

    private static function ensureMysqlTableCollation(Db $db, string $table, string $collation): void
    {
        $current = self::mysqlTableCollation($db, $table);
        if ($current === '' || strtolower($current) === strtolower($collation)) {
            return;
        }

        $db->query(
            'ALTER TABLE ' . self::quote($table, 'mysql')
            . ' CONVERT TO CHARACTER SET utf8mb4 COLLATE ' . $collation,
            Db::WRITE
        );
    }

    private static function ensureMysqlColumnDefinitions(Db $db, string $tableKey, string $table): void
    {
        $columnMap = self::mysqlColumns($db, $table);
        $mysqlMeta = (array) (self::criticalSchema()[$tableKey]['mysql'] ?? []);
        $definitions = (array) ($mysqlMeta['definitions'] ?? []);

        foreach ($definitions as $column => $definition) {
            $actualType = self::normalizeMysqlType((string) ($columnMap[$column]['Type'] ?? ''));
            $expectedType = self::mysqlDefinitionType((string) $definition);
            if ($actualType === '') {
                continue;
            }

            if ($expectedType !== '' && str_contains($actualType, $expectedType)) {
                continue;
            }

            $db->query(
                'ALTER TABLE ' . self::quote($table, 'mysql')
                . ' MODIFY COLUMN ' . $definition,
                Db::WRITE
            );
        }
    }

    private static function mysqlDefinitionType(string $definition): string
    {
        if (preg_match('/^`[^`]+`\s+(.+?)(?:\s+NOT\s+NULL|\s+DEFAULT|\s+NULL|\s+AUTO_INCREMENT|$)/i', trim($definition), $matches) !== 1) {
            return '';
        }

        return self::normalizeMysqlType((string) ($matches[1] ?? ''));
    }

    private static function normalizeMysqlType(string $type): string
    {
        $type = strtolower(trim($type));
        return (string) preg_replace('/^(tinyint|smallint|mediumint|int|integer|bigint)\(\d+\)/', '$1', $type);
    }

    public static function mysqlTableCollation(Db $db, string $table): string
    {
        $row = self::mysqlTableStatus($db, $table);
        return trim((string) ($row['Collation'] ?? ''));
    }

    private static function mysqlCollation(Db $db, string $dialect): string
    {
        if ($dialect !== 'mysql') {
            return 'utf8mb4_unicode_ci';
        }

        try {
            $version = $db->getVersion(Db::READ);

            foreach (['contents', 'options', 'users'] as $tableKey) {
                $table = $db->getPrefix() . $tableKey;
                $collation = self::mysqlTableCollation($db, $table);
                if ($collation !== '') {
                    return DbInfo::resolveMysqlCollation('utf8mb4', $version, $collation);
                }
            }

            return DbInfo::resolveMysqlCollation('utf8mb4', $version);
        } catch (\Throwable) {
        }

        return 'utf8mb4_unicode_ci';
    }
}
