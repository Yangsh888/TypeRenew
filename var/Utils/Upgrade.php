<?php

namespace Utils;

use Typecho\Db;
use Utils\Migration\Runner;
use Utils\Migration\SchemaManager;
use Widget\Options;

/**
 * 升级程序
 *
 * @category typecho
 * @package Upgrade
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Upgrade
{
    private static array $backupStack = [];

    public static function backup(Db $db, string $table, string $where = ''): bool
    {
        try {
            $rows = $db->fetchAll($db->select()->from($table)->where($where ?: '1=1'));
            if (empty($rows)) {
                return true;
            }
            $key = $table . ':' . ($where ?: 'all');
            self::$backupStack[$key] = [
                'table' => $table,
                'where' => $where,
                'data' => $rows,
                'time' => time()
            ];
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function rollback(): int
    {
        $count = 0;
        foreach (self::$backupStack as $key => $backup) {
            try {
                $table = $backup['table'];
                $where = $backup['where'];
                $db = Db::get();
                if ($where !== '' && $where !== '1=1') {
                    $db->query($db->update($table)->rows($backup['data'][0])->where($where));
                } else {
                    foreach ($backup['data'] as $row) {
                        $db->query($db->update($table)->rows($row)->where('id = ?', $row['id'] ?? 0));
                    }
                }
                $count++;
            } catch (\Throwable $e) {
                continue;
            }
        }
        self::$backupStack = [];
        return $count;
    }

    public static function inspectCriticalSchema(Db $db, Options $options): array
    {
        return SchemaManager::inspectCriticalSchema($db, $options);
    }

    public static function repairCriticalSchema(Db $db, Options $options): array
    {
        return SchemaManager::repairCriticalSchema($db, $options);
    }

    public static function runPendingMigrations(Db $db, string $currentVersion): array
    {
        return Runner::runPending($db, $currentVersion);
    }
}
