<?php

namespace Utils;

use Typecho\Db;
use Utils\Migration\Runner;
use Utils\Migration\SchemaManager;
use Widget\Options;

class Upgrade
{
    public static function inspectCriticalSchema(Db $db, Options $options): array
    {
        return SchemaManager::inspectCriticalSchema($db);
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
