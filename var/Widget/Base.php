<?php

namespace Widget;

use Typecho\Config;
use Typecho\Db;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

abstract class Base extends Widget
{
    protected const INIT_DB = 0b0001;
    protected const INIT_USER = 0b0010;
    protected const INIT_SECURITY = 0b0100;
    protected const INIT_OPTIONS = 0b1000;
    protected const INIT_ALL = 0b1111;
    protected const INIT_NONE = 0;

    protected Options $options;
    protected User $user;
    protected Security $security;
    protected Db $db;

    protected function init()
    {
        $components = self::INIT_ALL;
        $this->initComponents($components);

        if ($components != self::INIT_NONE) {
            $this->db = Db::get();
        }

        if ($components & self::INIT_USER) {
            $this->user = User::alloc();
        }

        if ($components & self::INIT_OPTIONS) {
            $this->options = Options::alloc();
        }

        if ($components & self::INIT_SECURITY) {
            $this->security = Security::alloc();
        }

        $this->initParameter($this->parameter);
    }

    protected function initComponents(int &$components)
    {
    }

    protected function initParameter(Config $parameter)
    {
    }
}
