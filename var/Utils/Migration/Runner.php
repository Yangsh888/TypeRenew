<?php

namespace Utils\Migration;

use Typecho\Common;
use Typecho\Db;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Runner
{
    public static function runPending(Db $db, string $currentVersion): array
    {
        $messages = [];
        $applied = [];

        foreach (Registry::all() as $step) {
            $version = $step->version();
            if (version_compare($currentVersion, $version, '>=')) {
                continue;
            }

            $options = Options::allocWithAlias($version);
            try {
                $result = $step->up($db, $options);
                if (!empty($result)) {
                    $messages[] = $result;
                }

                self::updateGenerator($db, $version);
                $currentVersion = $version;
                $applied[] = [
                    'version' => $version,
                    'step' => get_class($step),
                    'message' => $result
                ];
            } finally {
                Options::destroy($version);
            }
        }

        self::updateGenerator($db, Common::VERSION);

        return [
            'messages' => $messages,
            'applied' => $applied
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
}
