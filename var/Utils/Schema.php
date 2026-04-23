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

    public static function ensureRenewShield(Db $db): void
    {
        self::ensureTables($db, ['renew_shield_logs', 'renew_shield_state']);
    }

    public static function ensureRenewGo(Db $db): void
    {
        self::ensureTables($db, ['renew_go_logs']);
    }

    public static function ensureRenewSeo(Db $db): void
    {
        self::ensureTables($db, ['renew_seo_logs', 'renew_seo_404']);
    }

    public static function ensureCoreIndexes(Db $db): void
    {
        $prefix = $db->getPrefix();

        self::ensureIndex($db, $prefix . 'comments', $prefix . 'comments_status', ['status']);
        self::ensureIndex($db, $prefix . 'comments', $prefix . 'comments_cid_status', ['cid', 'status']);
        self::ensureIndex($db, $prefix . 'comments', $prefix . 'comments_owner_status', ['ownerId', 'status']);
        self::ensureIndex($db, $prefix . 'comments', $prefix . 'comments_parent', ['parent']);

        self::ensureIndex($db, $prefix . 'contents', $prefix . 'contents_type_status_created', ['type', 'status', 'created']);
        self::ensureIndex($db, $prefix . 'contents', $prefix . 'contents_author_type_status_created', ['authorId', 'type', 'status', 'created']);
        self::ensureIndex($db, $prefix . 'contents', $prefix . 'contents_parent_type', ['parent', 'type']);

        self::ensureIndex($db, $prefix . 'metas', $prefix . 'metas_type_slug', ['type', 'slug']);
        self::ensureIndex($db, $prefix . 'metas', $prefix . 'metas_type_parent_order', ['type', 'parent', 'order']);
        self::ensureIndex($db, $prefix . 'relationships', $prefix . 'relationships_mid', ['mid']);
    }

    public static function ensureUserPasswordStorage(Db $db): void
    {
        $dialect = self::dialect($db);
        $table = $db->getPrefix() . 'users';

        if ($dialect === 'sqlite') {
            return;
        }

        if ($dialect === 'pgsql') {
            $db->query(
                'ALTER TABLE ' . self::quote($table, $dialect)
                . ' ALTER COLUMN "password" TYPE VARCHAR(255)',
                Db::WRITE
            );
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
                        . '`lastError` varchar(500) NOT NULL default "",'
                        . '`dedupeKey` char(40) NOT NULL default "",'
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
                        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=' . $mysqlCollation,
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

            case 'renew_go_logs':
                return match ($dialect) {
                    'sqlite' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                        . '"ip" TEXT NOT NULL,'
                        . '"action" TEXT NOT NULL,'
                        . '"result" TEXT NOT NULL,'
                        . '"target" TEXT DEFAULT NULL,'
                        . '"referer" TEXT DEFAULT NULL,'
                        . '"created_at" INTEGER NOT NULL'
                        . ')',
                    'pgsql' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" BIGSERIAL PRIMARY KEY,'
                        . '"ip" VARCHAR(45) NOT NULL,'
                        . '"action" VARCHAR(24) NOT NULL,'
                        . '"result" VARCHAR(16) NOT NULL,'
                        . '"target" TEXT DEFAULT NULL,'
                        . '"referer" TEXT DEFAULT NULL,'
                        . '"created_at" INTEGER NOT NULL'
                        . ')',
                    default => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '`id` bigint unsigned NOT NULL auto_increment,'
                        . '`ip` varchar(45) NOT NULL,'
                        . '`action` varchar(24) NOT NULL,'
                        . '`result` varchar(16) NOT NULL,'
                        . '`target` varchar(512) DEFAULT NULL,'
                        . '`referer` varchar(512) DEFAULT NULL,'
                        . '`created_at` int unsigned NOT NULL,'
                        . 'PRIMARY KEY (`id`)'
                        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=' . $mysqlCollation,
                };

            case 'renew_shield_logs':
                return match ($dialect) {
                    'sqlite' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                        . '"scope" TEXT NOT NULL,'
                        . '"action" TEXT NOT NULL,'
                        . '"decision" TEXT NOT NULL,'
                        . '"rule_key" TEXT NOT NULL,'
                        . '"score" INTEGER NOT NULL DEFAULT 0,'
                        . '"method" TEXT NOT NULL,'
                        . '"ip" TEXT DEFAULT NULL,'
                        . '"path" TEXT DEFAULT NULL,'
                        . '"ua" TEXT DEFAULT NULL,'
                        . '"message" TEXT NOT NULL,'
                        . '"payload" TEXT DEFAULT NULL,'
                        . '"created_at" INTEGER NOT NULL'
                        . ')',
                    'pgsql' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" BIGSERIAL PRIMARY KEY,'
                        . '"scope" VARCHAR(24) NOT NULL,'
                        . '"action" VARCHAR(24) NOT NULL,'
                        . '"decision" VARCHAR(16) NOT NULL,'
                        . '"rule_key" VARCHAR(64) NOT NULL,'
                        . '"score" INT NOT NULL DEFAULT 0,'
                        . '"method" VARCHAR(12) NOT NULL,'
                        . '"ip" VARCHAR(45) DEFAULT NULL,'
                        . '"path" TEXT DEFAULT NULL,'
                        . '"ua" TEXT DEFAULT NULL,'
                        . '"message" VARCHAR(255) NOT NULL,'
                        . '"payload" TEXT DEFAULT NULL,'
                        . '"created_at" INTEGER NOT NULL'
                        . ')',
                    default => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '`id` bigint unsigned NOT NULL auto_increment,'
                        . '`scope` varchar(24) NOT NULL,'
                        . '`action` varchar(24) NOT NULL,'
                        . '`decision` varchar(16) NOT NULL,'
                        . '`rule_key` varchar(64) NOT NULL,'
                        . '`score` int NOT NULL DEFAULT 0,'
                        . '`method` varchar(12) NOT NULL,'
                        . '`ip` varchar(45) DEFAULT NULL,'
                        . '`path` varchar(1024) DEFAULT NULL,'
                        . '`ua` varchar(512) DEFAULT NULL,'
                        . '`message` varchar(255) NOT NULL,'
                        . '`payload` text DEFAULT NULL,'
                        . '`created_at` int unsigned NOT NULL,'
                        . 'PRIMARY KEY (`id`)'
                        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=' . $mysqlCollation,
                };

            case 'renew_shield_state':
                return match ($dialect) {
                    'sqlite' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                        . '"name_hash" TEXT NOT NULL UNIQUE,'
                        . '"value" TEXT DEFAULT NULL,'
                        . '"expires_at" INTEGER NOT NULL DEFAULT 0'
                        . ')',
                    'pgsql' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" BIGSERIAL PRIMARY KEY,'
                        . '"name_hash" CHAR(40) NOT NULL UNIQUE,'
                        . '"value" TEXT DEFAULT NULL,'
                        . '"expires_at" INTEGER NOT NULL DEFAULT 0'
                        . ')',
                    default => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '`id` bigint unsigned NOT NULL auto_increment,'
                        . '`name_hash` char(40) NOT NULL,'
                        . '`value` mediumtext DEFAULT NULL,'
                        . '`expires_at` int unsigned NOT NULL DEFAULT 0,'
                        . 'PRIMARY KEY (`id`),'
                        . 'UNIQUE KEY `uniq_name_hash` (`name_hash`)'
                        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=' . $mysqlCollation,
                };

            case 'renew_seo_logs':
                return match ($dialect) {
                    'sqlite' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                        . '"channel" TEXT NOT NULL,'
                        . '"action" TEXT NOT NULL,'
                        . '"level" TEXT NOT NULL,'
                        . '"target" TEXT DEFAULT NULL,'
                        . '"message" TEXT NOT NULL,'
                        . '"payload" TEXT DEFAULT NULL,'
                        . '"created_at" INTEGER NOT NULL'
                        . ')',
                    'pgsql' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" BIGSERIAL PRIMARY KEY,'
                        . '"channel" VARCHAR(24) NOT NULL,'
                        . '"action" VARCHAR(32) NOT NULL,'
                        . '"level" VARCHAR(16) NOT NULL,'
                        . '"target" TEXT DEFAULT NULL,'
                        . '"message" VARCHAR(255) NOT NULL,'
                        . '"payload" TEXT DEFAULT NULL,'
                        . '"created_at" INTEGER NOT NULL'
                        . ')',
                    default => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '`id` bigint unsigned NOT NULL auto_increment,'
                        . '`channel` varchar(24) NOT NULL,'
                        . '`action` varchar(32) NOT NULL,'
                        . '`level` varchar(16) NOT NULL,'
                        . '`target` varchar(512) DEFAULT NULL,'
                        . '`message` varchar(255) NOT NULL,'
                        . '`payload` text DEFAULT NULL,'
                        . '`created_at` int unsigned NOT NULL,'
                        . 'PRIMARY KEY (`id`)'
                        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=' . $mysqlCollation,
                };

            case 'renew_seo_404':
                return match ($dialect) {
                    'sqlite' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" INTEGER PRIMARY KEY AUTOINCREMENT,'
                        . '"path_hash" TEXT NOT NULL UNIQUE,'
                        . '"path" TEXT NOT NULL,'
                        . '"full_url" TEXT NOT NULL,'
                        . '"referer" TEXT DEFAULT NULL,'
                        . '"ip" TEXT DEFAULT NULL,'
                        . '"ua" TEXT DEFAULT NULL,'
                        . '"hits" INTEGER NOT NULL DEFAULT 1,'
                        . '"first_seen" INTEGER NOT NULL,'
                        . '"last_seen" INTEGER NOT NULL'
                        . ')',
                    'pgsql' => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '"id" BIGSERIAL PRIMARY KEY,'
                        . '"path_hash" CHAR(40) NOT NULL UNIQUE,'
                        . '"path" TEXT NOT NULL,'
                        . '"full_url" TEXT NOT NULL,'
                        . '"referer" TEXT DEFAULT NULL,'
                        . '"ip" VARCHAR(45) DEFAULT NULL,'
                        . '"ua" TEXT DEFAULT NULL,'
                        . '"hits" INTEGER NOT NULL DEFAULT 1,'
                        . '"first_seen" INTEGER NOT NULL,'
                        . '"last_seen" INTEGER NOT NULL'
                        . ')',
                    default => 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('
                        . '`id` bigint unsigned NOT NULL auto_increment,'
                        . '`path_hash` char(40) NOT NULL UNIQUE,'
                        . '`path` varchar(512) NOT NULL,'
                        . '`full_url` varchar(1024) NOT NULL,'
                        . '`referer` varchar(1024) DEFAULT NULL,'
                        . '`ip` varchar(45) DEFAULT NULL,'
                        . '`ua` varchar(512) DEFAULT NULL,'
                        . '`hits` int unsigned NOT NULL DEFAULT 1,'
                        . '`first_seen` int unsigned NOT NULL,'
                        . '`last_seen` int unsigned NOT NULL,'
                        . 'PRIMARY KEY (`id`)'
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
                ['name' => $name('idx_locked', 'lockedUntil'), 'columns' => ['lockedUntil']],
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
            'renew_go_logs' => [
                ['name' => $name('idx_ip_action_created', 'ip_action_created'), 'columns' => ['ip', 'action', 'created_at']],
                ['name' => $name('idx_created', 'created'), 'columns' => ['created_at']],
            ],
            'renew_shield_logs' => [
                ['name' => $name('idx_scope_created', 'scope_created'), 'columns' => ['scope', 'created_at']],
                ['name' => $name('idx_decision_created', 'decision_created'), 'columns' => ['decision', 'created_at']],
                ['name' => $name('idx_ip_created', 'ip_created'), 'columns' => ['ip', 'created_at']],
                ['name' => $name('idx_rule_created', 'rule_created'), 'columns' => ['rule_key', 'created_at']],
                ['name' => $name('idx_created', 'created'), 'columns' => ['created_at']],
            ],
            'renew_shield_state' => [
                ['name' => $name('uniq_name_hash', 'name_hash'), 'columns' => ['name_hash'], 'unique' => true],
                ['name' => $name('idx_expires', 'expires'), 'columns' => ['expires_at']],
            ],
            'renew_seo_logs' => [
                ['name' => $name('idx_channel_created', 'channel_created'), 'columns' => ['channel', 'created_at']],
                ['name' => $name('idx_created', 'created'), 'columns' => ['created_at']],
            ],
            'renew_seo_404' => [
                ['name' => $name('idx_last_seen', 'last_seen'), 'columns' => ['last_seen']],
                ['name' => $name('idx_hits', 'hits'), 'columns' => ['hits']],
            ],
            default => [],
        };
    }

    private static function ensureIndex(Db $db, string $table, string $index, array $columns, bool $unique = false): void
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

    private static function indexExists(Db $db, string $table, string $index): bool
    {
        $dialect = self::dialect($db);

        return match ($dialect) {
            'sqlite' => self::sqliteIndexExists($db, $table, $index),
            'pgsql' => self::pgsqlIndexExists($db, $table, $index),
            default => self::mysqlIndexExists($db, $table, $index),
        };
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
            $rows = $db->fetchAll('PRAGMA index_list(' . self::quote($table, 'sqlite') . ')');
            foreach ($rows as $row) {
                if (($row['name'] ?? null) === $index) {
                    return true;
                }
            }

            return false;
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

    private static function quote(string $name, string $dialect): string
    {
        $escaped = str_replace($dialect === 'mysql' ? '`' : '"', $dialect === 'mysql' ? '``' : '""', $name);

        return $dialect === 'mysql' ? '`' . $escaped . '`' : '"' . $escaped . '"';
    }

    private static function sqlString(string $value): string
    {
        return '\'' . str_replace('\'', '\'\'', $value) . '\'';
    }

    private static function mysqlCollation(Db $db, string $dialect): string
    {
        if ($dialect !== 'mysql') {
            return 'utf8mb4_unicode_ci';
        }

        try {
            foreach (['contents', 'options', 'users'] as $tableKey) {
                $table = $db->getPrefix() . $tableKey;
                $row = $db->fetchRow('SHOW TABLE STATUS LIKE ' . self::sqlString($table));
                $collation = trim((string) ($row['Collation'] ?? ''));
                if ($collation !== '') {
                    return $collation;
                }
            }

            $version = $db->getVersion(Db::READ);
            if (stripos($version, 'mariadb') === false
                && preg_match('/\d+\.\d+(?:\.\d+)?/', $version, $matches) === 1
                && version_compare($matches[0], '8.0.0', '>=')
            ) {
                return 'utf8mb4_0900_ai_ci';
            }
        } catch (\Throwable) {
        }

        return 'utf8mb4_unicode_ci';
    }
}
