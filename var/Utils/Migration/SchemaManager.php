<?php

namespace Utils\Migration;

use Typecho\Common;
use Typecho\Db;
use Utils\Schema;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class SchemaManager
{
    public static function syncCurrentRelease(Db $db, array $activatedPlugins = []): array
    {
        self::ensureMailInfrastructure($db);
        Schema::ensureCoreIndexes($db);
        Schema::ensureUserPasswordStorage($db);

        if (in_array('RenewGo', $activatedPlugins, true)) {
            Schema::ensureRenewGo($db);
        }

        if (in_array('RenewSEO', $activatedPlugins, true)) {
            Schema::ensureRenewSeo($db);
        }

        self::updateGenerator($db, Common::VERSION);

        return [
            'messages' => [_t('当前版本所需的数据库结构已同步')],
        ];
    }

    public static function inspectCriticalSchema(Db $db): array
    {
        $items = [];
        $missing = [];
        $prefix = (string) $db->getPrefix();
        $dialect = Schema::dialect($db);
        $expectedCollation = $dialect === 'mysql' ? Schema::detectMysqlCollation($db) : '';
        $tables = Schema::criticalSchema();

        foreach ($tables as $key => $meta) {
            $exists = self::tableExists($db, 'table.' . $key);
            $missingColumns = $exists
                ? self::missingColumns($db, $prefix . $key, Schema::criticalColumns($key))
                : [];
            $missingIndexes = $exists
                ? self::missingIndexes($db, $prefix . $key, Schema::criticalIndexes($db, $key, $prefix . $key))
                : [];
            $typeMismatches = ($exists && $dialect === 'mysql')
                ? Schema::mysqlTypeMismatches($db, $prefix . $key, (array) (($meta['mysql']['definitions'] ?? [])))
                : [];
            $tableCollation = ($exists && $dialect === 'mysql')
                ? Schema::mysqlTableCollation($db, $prefix . $key)
                : '';
            $collationOk = $tableCollation === '' || $expectedCollation === ''
                ? true
                : strtolower($tableCollation) === strtolower($expectedCollation);
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
                'missingColumns' => $missingColumns,
                'missingIndexes' => $missingIndexes,
                'typeMismatches' => $typeMismatches,
                'tableCollation' => $tableCollation,
                'expectedCollation' => $expectedCollation,
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

    public static function repairCriticalSchema(Db $db): array
    {
        $before = self::inspectCriticalSchema($db);
        self::ensureMailInfrastructure($db);
        Schema::repairMailInfra($db);
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

    public static function inspectMysqlUpgradeRisks(Db $db): array
    {
        if (Schema::dialect($db) !== 'mysql') {
            return [
                'supported' => false,
                'healthy' => true,
                'items' => []
            ];
        }

        $rawVersion = $db->getVersion(Db::READ);
        $version = \Utils\DbInfo::extractVersion($rawVersion);
        $collation = Schema::detectMysqlCollation($db);
        $items = [];

        $items[] = [
            'key' => 'mysql_version',
            'label' => '数据库版本',
            'status' => $version !== '' ? 'ok' : 'warning',
            'detail' => $rawVersion !== '' ? $rawVersion : '无法识别当前版本',
        ];

        $legacyIndexLimit = $version !== '' && version_compare($version, '5.7.7', '<');
        $items[] = [
            'key' => 'legacy_index_limit',
            'label' => '旧 InnoDB 索引长度限制',
            'status' => $legacyIndexLimit ? 'warning' : 'ok',
            'detail' => $legacyIndexLimit
                ? '当前版本可能仍受 767-byte 索引长度限制，邮件退订表唯一索引需重点确认'
                : '当前版本不受旧 767-byte 索引长度限制影响',
        ];

        $mailUnsubTable = $db->getPrefix() . 'mail_unsub';
        $mailUnsubExists = self::tableExists($db, 'table.mail_unsub');
        $mailUnsubCollation = $mailUnsubExists ? Schema::mysqlTableCollation($db, $mailUnsubTable) : '';
        $mailUnsubMismatch = $mailUnsubExists && $mailUnsubCollation !== '' && strtolower($mailUnsubCollation) !== strtolower($collation);
        $items[] = [
            'key' => 'mail_unsub_collation',
            'label' => 'mail_unsub 排序规则',
            'status' => !$mailUnsubExists || !$mailUnsubMismatch ? 'ok' : 'warning',
            'detail' => !$mailUnsubExists
                ? '表不存在，升级时会按当前版本创建'
                : ($mailUnsubMismatch
                    ? '当前为 ' . $mailUnsubCollation . '，目标推荐为 ' . $collation
                    : '当前已与目标排序规则一致'),
        ];

        $mailUnsubDuplicates = self::mailUnsubDuplicateGroups($db);
        $items[] = [
            'key' => 'mail_unsub_duplicates',
            'label' => 'mail_unsub 唯一值冲突',
            'status' => $mailUnsubDuplicates === [] ? 'ok' : 'warning',
            'detail' => $mailUnsubDuplicates === []
                ? '未发现 email + scope 冲突'
                : '发现 ' . count($mailUnsubDuplicates) . ' 组 email + scope 重复，修复索引前需先清理',
            'samples' => $mailUnsubDuplicates,
        ];

        $userDuplicates = self::usersMailDuplicateGroups($db);
        $items[] = [
            'key' => 'users_mail_duplicates',
            'label' => 'users 邮箱唯一值冲突',
            'status' => $userDuplicates === [] ? 'ok' : 'warning',
            'detail' => $userDuplicates === []
                ? '未发现 users.mail 重复'
                : '发现 ' . count($userDuplicates) . ' 组重复邮箱，排序规则升级后可能触发唯一键冲突',
            'samples' => $userDuplicates,
        ];

        $healthy = true;
        foreach ($items as $item) {
            if (($item['status'] ?? 'ok') !== 'ok') {
                $healthy = false;
                break;
            }
        }

        return [
            'supported' => true,
            'healthy' => $healthy,
            'version' => $rawVersion,
            'collation' => $collation,
            'items' => $items,
        ];
    }

    private static function ensureMailInfrastructure(Db $db): void
    {
        Schema::ensureMailInfra($db);

        foreach (self::defaultMailOptions() as $name => $value) {
            $exists = $db->fetchRow(
                $db->select('name')->from('table.options')->where('name = ? AND user = 0', $name)->limit(1)
            );
            if ($exists) {
                continue;
            }

            $db->query($db->insert('table.options')->rows(['name' => $name, 'user' => 0, 'value' => $value]));
        }
    }

    private static function defaultMailOptions(): array
    {
        return [
            'mailEnable' => '0',
            'mailTransport' => 'smtp',
            'mailAdmin' => '',
            'mailFrom' => '',
            'mailFromName' => '',
            'mailSmtpHost' => '',
            'mailSmtpPort' => '25',
            'mailSmtpUser' => '',
            'mailSmtpPass' => '',
            'mailSmtpSecure' => '',
            'mailQueueMode' => 'async',
            'mailAsyncIps' => '',
            'mailCronKey' => Common::randString(32),
            'mailBatchSize' => '50',
            'mailMaxAttempts' => '3',
            'mailKeepDays' => '30',
            'mailNotifyOwner' => '1',
            'mailNotifyGuest' => '1',
            'mailNotifyPending' => '1',
            'mailNotifyMe' => '0',
            'mailSubjectOwner' => '',
            'mailSubjectGuest' => '',
            'mailSubjectPending' => ''
        ];
    }

    private static function updateGenerator(Db $db, string $version): void
    {
        $db->query(
            $db->update('table.options')
                ->rows(['value' => Common::generator($version)])
                ->where('name = ?', 'generator')
        );
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
            if (!Schema::indexExists($db, $table, (string) $index)) {
                $missing[] = (string) $index;
            }
        }

        return $missing;
    }


    private static function mailUnsubDuplicateGroups(Db $db): array
    {
        if (!self::tableExists($db, 'table.mail_unsub')) {
            return [];
        }

        try {
            $rows = $db->fetchAll(
                'SELECT email, scope, COUNT(*) AS num'
                . ' FROM ' . $db->getPrefix() . 'mail_unsub'
                . ' GROUP BY email, scope'
                . ' HAVING COUNT(*) > 1'
                . ' ORDER BY num DESC, email ASC'
                . ' LIMIT 5'
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'email' => (string) ($row['email'] ?? ''),
                'scope' => (string) ($row['scope'] ?? ''),
                'count' => (int) ($row['num'] ?? 0),
            ];
        }, $rows);
    }

    private static function usersMailDuplicateGroups(Db $db): array
    {
        if (!self::tableExists($db, 'table.users')) {
            return [];
        }

        try {
            $rows = $db->fetchAll(
                'SELECT mail, COUNT(*) AS num'
                . ' FROM ' . $db->getPrefix() . 'users'
                . ' WHERE mail IS NOT NULL AND mail <> \'\''
                . ' GROUP BY mail'
                . ' HAVING COUNT(*) > 1'
                . ' ORDER BY num DESC, mail ASC'
                . ' LIMIT 5'
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'mail' => (string) ($row['mail'] ?? ''),
                'count' => (int) ($row['num'] ?? 0),
            ];
        }, $rows);
    }
}
