<?php

namespace Utils\Migration\Steps;

use Typecho\Db;
use Utils\Migration\StepInterface;
use Utils\Schema;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class PasswordStorageStep implements StepInterface
{
    public function version(): string
    {
        return '1.4.1';
    }

    public function up(Db $db, Options $options)
    {
        Schema::ensureUserPasswordStorage($db);

        return _t('密码存储字段已同步为现代哈希长度');
    }
}
