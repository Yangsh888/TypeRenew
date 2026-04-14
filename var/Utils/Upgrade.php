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
