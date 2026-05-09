<?php

namespace Widget;

use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Logout extends Users implements ActionInterface
{
    public function action()
    {
        // protect
        $this->security->protect();

        $this->user->logout();
        self::pluginHandle()->call('logout');
        @session_destroy();
        $this->response->goBack(null, $this->options->index);
    }
}
