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

}
