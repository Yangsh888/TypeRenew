<?php

namespace Utils\Migration\Steps;

use Typecho\Db;
use Utils\Migration\StepInterface;
use Utils\Schema;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class IndexTuneStep implements StepInterface
{
    public function version(): string
    {
        return '1.4.2';
    }

    public function up(Db $db, Options $options)
    {
        Schema::ensureCoreIndexes($db);
        Schema::ensureMailInfra($db);

        return _t('已补齐核心复合索引与邮件队列索引');
    }
}
