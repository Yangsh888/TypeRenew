<?php

namespace Widget;

use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Action extends Widget implements ActionInterface
{
    private array $map = [
        'ajax'                     => '\Widget\Ajax',
        'login'                    => '\Widget\Login',
        'logout'                   => '\Widget\Logout',
        'register'                 => '\Widget\Register',
        'forgot'                   => '\Widget\Forgot',
        'reset'                    => '\Widget\Reset',
        'upgrade'                  => '\Widget\Upgrade',
        'upgrade-package'          => '\Widget\Upgrade\Package',
        'upload'                   => '\Widget\Upload',
        'service'                  => '\Widget\Service',
        'xmlrpc'                   => '\Widget\XmlRpc',
        'comments-edit'            => '\Widget\Comments\Edit',
        'contents-page-edit'       => '\Widget\Contents\Page\Edit',
        'contents-post-edit'       => '\Widget\Contents\Post\Edit',
        'contents-attachment-edit' => '\Widget\Contents\Attachment\Edit',
        'metas-category-edit'      => '\Widget\Metas\Category\Edit',
        'metas-tag-edit'           => '\Widget\Metas\Tag\Edit',
        'options-discussion'       => '\Widget\Options\Discussion',
        'options-general'          => '\Widget\Options\General',
        'options-permalink'        => '\Widget\Options\Permalink',
        'options-reading'          => '\Widget\Options\Reading',
        'options-cache'            => '\Widget\Options\Cache',
        'options-mail'             => '\Widget\Options\Mail',
        'plugins-edit'             => '\Widget\Plugins\Edit',
        'themes-edit'              => '\Widget\Themes\Edit',
        'users-edit'               => '\Widget\Users\Edit',
        'users-profile'            => '\Widget\Users\Profile',
        'backup'                   => '\Widget\Backup',
        'mail'                     => '\Widget\Mail'
    ];

    private function dispatchAction(): void
    {
        $action = $this->request->get('action');
        $actionTable = array_merge($this->map, Options::alloc()->actionTable);
        $widgetName = $actionTable[$action] ?? null;

        if (is_string($widgetName) && class_exists($widgetName)) {
            $widget = self::widget($widgetName);

            if ($widget instanceof ActionInterface) {
                $widget->action();
                return;
            }
        }

        throw new Widget\Exception(_t('请求的地址不存在'), 404);
    }

    public function execute()
    {
    }

    public function action()
    {
        $this->dispatchAction();
    }
}
