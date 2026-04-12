<?php

namespace Utils\Migration;

use Typecho\Db;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

interface StepInterface
{
    public function version(): string;

    public function up(Db $db, Options $options);
}
