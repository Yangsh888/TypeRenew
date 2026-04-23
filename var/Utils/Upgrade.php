<?php

namespace Utils;

use Utils\Migration\SchemaManager;

class Upgrade extends SchemaManager
{
    public static function runPendingMigrations(\Typecho\Db $db, array $activatedPlugins = []): array
    {
        return parent::syncCurrentRelease($db, $activatedPlugins);
    }
}
