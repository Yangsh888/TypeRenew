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
            'columns' => ['id', 'type', 'status', 'attempts', 'lockedUntil', 'sendAt', 'created', 'updated', 'lastError', 'dedupeKey', 'payload']
        ],
        'mail_unsub' => [
            'label' => '邮件退订表',
            'columns' => ['id', 'email', 'scope', 'created']
        ],
        'password_resets' => [
            'label' => '密码重置表',
            'columns' => ['id', 'email', 'token', 'created', 'expires', 'used']
        ]
    ];

    public static function inspectCriticalSchema(Db $db, Options $options): array
    {
        $items = [];
        $missing = [];
        $prefix = (string) $db->getPrefix();

        foreach (self::CRITICAL_TABLES as $key => $meta) {
            $exists = self::tableExists($db, 'table.' . $key);
            $missingColumns = $exists
                ? self::missingColumns($db, $prefix . $key, (array) ($meta['columns'] ?? []))
                : [];
            $columnsOk = $exists && $missingColumns === [];
            $item = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'table' => $prefix . $key,
                'exists' => $exists,
                'columnsOk' => $columnsOk,
                'missingColumns' => $missingColumns,
                'status' => !$exists ? 'missing_table' : ($columnsOk ? 'ok' : 'missing_columns')
            ];
            $items[] = $item;

            if (!$exists || !$columnsOk) {
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
        $before = self::inspectCriticalSchema($db, $options);
        (new InstallMailAndResetInfrastructureStep())->up($db, $options);
        $after = self::inspectCriticalSchema($db, $options);

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
}
