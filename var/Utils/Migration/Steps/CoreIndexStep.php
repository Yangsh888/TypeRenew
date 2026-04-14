<?php

namespace Utils\Migration\Steps;

use Typecho\Db;
use Utils\Migration\StepInterface;
use Utils\Schema;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class CoreIndexStep implements StepInterface
{
    public function version(): string
    {
        return '1.4.1';
    }

    public function up(Db $db, Options $options)
    {
        Schema::ensureCoreIndexes($db);

        $activated = is_array($options->plugins['activated'] ?? null)
            ? array_keys($options->plugins['activated'])
            : [];

        if (in_array('RenewGo', $activated, true)) {
            Schema::ensureRenewGo($db);
        }

        if (in_array('RenewSEO', $activated, true)) {
            Schema::ensureRenewSeo($db);
        }

        return _t('核心索引与插件扩展表已同步');
    }
}
