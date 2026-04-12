<?php

namespace Utils\Migration;

use Typecho\Db;
use Utils\Migration\Steps\InstallMailAndResetInfrastructureStep;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class SchemaManager
{
    private const CRITICAL_TABLES = [
        'mail_queue' => '邮件队列表',
        'mail_unsub' => '邮件退订表',
        'password_resets' => '密码重置表'
    ];

    public static function inspectCriticalSchema(Db $db, Options $options): array
    {
        $items = [];
        $missing = [];

        foreach (self::CRITICAL_TABLES as $key => $label) {
            $exists = self::tableExists($db, 'table.' . $key);
            $item = [
                'key' => $key,
                'label' => $label,
                'table' => (string) $options->dbPrefix . $key,
                'exists' => $exists
            ];
            $items[] = $item;

            if (!$exists) {
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
        $message = (new InstallMailAndResetInfrastructureStep())->up($db, $options);
        $after = self::inspectCriticalSchema($db, $options);

        $repaired = [];
        foreach ($before['missing'] as $item) {
            foreach ($after['items'] as $afterItem) {
                if ($afterItem['key'] === $item['key'] && $afterItem['exists']) {
                    $repaired[] = $afterItem;
                    break;
                }
            }
        }

        return [
            'healthy' => $after['healthy'],
            'before' => $before,
            'after' => $after,
            'repaired' => $repaired,
            'message' => $message
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
}
