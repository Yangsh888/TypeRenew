<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cache;
use Typecho\Cookie;
use Typecho\Date;
use Typecho\Db;
use Typecho\I18n;
use Typecho\Plugin;
use Typecho\Response;
use Typecho\Router;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Init extends Widget
{
    public function execute()
    {
        if (!defined('__TYPECHO_DEBUG__') || !__TYPECHO_DEBUG__) {
            set_exception_handler(function (\Throwable $exception) {
                Response::getInstance()->clean();
                ob_end_clean();

                ob_start(function ($content) {
                    Response::getInstance()->sendHeaders();
                    return $content;
                });

                if (404 == $exception->getCode()) {
                    ExceptionHandle::alloc();
                } else {
                    Common::error($exception);
                }

                exit;
            });
        }

        define('__TYPECHO_CLASS_ALIASES__', [
            'Typecho_Plugin_Interface'    => '\Typecho\Plugin\PluginInterface',
            'Typecho_Widget_Helper_Empty' => '\Typecho\Widget\Helper\EmptyClass',
            'Typecho_Db_Adapter_Mysql'    => '\Typecho\Db\Adapter\Mysqli',
            'Widget_Abstract'             => '\Widget\Base',
            'Widget_Abstract_Contents'    => '\Widget\Base\Contents',
            'Widget_Abstract_Comments'    => '\Widget\Base\Comments',
            'Widget_Abstract_Metas'       => '\Widget\Base\Metas',
            'Widget_Abstract_Options'     => '\Widget\Base\Options',
            'Widget_Abstract_Users'       => '\Widget\Base\Users',
            'Widget_Metas_Category_List'  => '\Widget\Metas\Category\Rows',
            'Widget_Contents_Page_List'   => '\Widget\Contents\Page\Rows',
            'Widget_Plugins_List'         => '\Widget\Plugins\Rows',
            'Widget_Themes_List'          => '\Widget\Themes\Rows',
            'Widget_Interface_Do'         => '\Widget\ActionInterface',
            'Widget_Do'                   => '\Widget\Action',
            'AutoP'                       => '\Utils\AutoP',
            'PasswordHash'                => '\Utils\Password',
            'Markdown'                    => '\Utils\Markdown',
            'HyperDown'                   => '\Utils\HyperDown',
            'Helper'                      => '\Utils\Helper',
            'Upgrade'                     => '\Utils\Upgrade'
        ]);

        $options = Options::alloc();
        Cache::init([
            'status' => (int) ($options->cacheStatus ?? 0),
            'driver' => (string) ($options->cacheDriver ?? 'redis'),
            'ttl' => (int) ($options->cacheTtl ?? 300),
            'prefix' => (string) ($options->cachePrefix ?? 'typerenew:cache:'),
            'redisHost' => (string) ($options->cacheRedisHost ?? '127.0.0.1'),
            'redisPort' => (int) ($options->cacheRedisPort ?? 6379),
            'redisPassword' => (string) ($options->cacheRedisPassword ?? ''),
            'redisDatabase' => (int) ($options->cacheRedisDatabase ?? 0)
        ]);

        if ($options->lang && $options->lang != 'zh_CN') {
            $dir = defined('__TYPECHO_LANG_DIR__') ? __TYPECHO_LANG_DIR__ : __TYPECHO_ROOT_DIR__ . '/usr/langs';
            I18n::setLang($dir . '/' . $options->lang . '.mo');
        }

        if (!defined('__TYPECHO_BACKUP_DIR__')) {
            define('__TYPECHO_BACKUP_DIR__', __TYPECHO_ROOT_DIR__ . '/usr/backups');
        }

        if (!defined('__TYPECHO_UPGRADE_DIR__')) {
            define('__TYPECHO_UPGRADE_DIR__', __TYPECHO_ROOT_DIR__ . '/var/Upgrade');
        }

        Cookie::setPrefix($options->rootUrl);
        if (defined('__TYPECHO_COOKIE_OPTIONS__')) {
            Cookie::setOptions(__TYPECHO_COOKIE_OPTIONS__);
        }

        Router::setRoutes($options->routingTable);
        Plugin::init($options->plugins);
        $this->response->setCharset($options->charset);
        $this->response->setContentType($options->contentType);
        Date::setTimezoneOffset($options->timezone);

        if (
            $options->installed
            && User::alloc()->hasLogin()
            && session_status() !== PHP_SESSION_ACTIVE
            && !headers_sent()
        ) {
            session_start();
        }
    }
}
