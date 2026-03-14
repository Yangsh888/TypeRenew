<?php

namespace Typecho;

use Widget\User;

/**
 * 命令面板类
 * 
 * 用于管理后台命令面板的命令注册、权限过滤和数据输出
 *
 * @package Typecho
 */
class Palette
{
    /**
     * 已注册的命令列表
     *
     * @var array
     */
    private static array $commands = [];

    /**
     * 命令分类配置
     *
     * @var array
     */
    private static array $categories = [
        'nav' => ['id' => 'nav', 'name' => '导航', 'order' => 10, 'icon' => 'i-external'],
        'create' => ['id' => 'create', 'name' => '创建', 'order' => 20, 'icon' => 'i-plus'],
        'manage' => ['id' => 'manage', 'name' => '管理', 'order' => 30, 'icon' => 'i-folder'],
        'settings' => ['id' => 'settings', 'name' => '设置', 'order' => 40, 'icon' => 'i-gear'],
        'appearance' => ['id' => 'appearance', 'name' => '外观', 'order' => 50, 'icon' => 'i-palette'],
        'tools' => ['id' => 'tools', 'name' => '工具', 'order' => 60, 'icon' => 'i-zap'],
        'interface' => ['id' => 'interface', 'name' => '界面', 'order' => 70, 'icon' => 'i-monitor'],
        'help' => ['id' => 'help', 'name' => '帮助', 'order' => 80, 'icon' => 'i-info']
    ];

    /**
     * 是否已初始化
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * 注册单个命令
     *
     * @param array $command 命令配置
     * @return void
     */
    public static function register(array $command): void
    {
        if (empty($command['id'])) {
            return;
        }

        $defaults = [
            'id' => '',
            'title' => '',
            'category' => 'tools',
            'icon' => null,
            'shortcut' => null,
            'keywords' => [],
            'access' => 'subscriber',
            'confirm' => null,
            'action' => null,
            'url' => null,
            'target' => null,
            'hidden' => false
        ];

        self::$commands[$command['id']] = array_merge($defaults, $command);
    }

    /**
     * 批量注册命令
     *
     * @param array $commands 命令列表
     * @return void
     */
    public static function registerAll(array $commands): void
    {
        foreach ($commands as $command) {
            self::register($command);
        }
    }

    /**
     * 获取所有命令
     *
     * @return array
     */
    public static function getCommands(): array
    {
        return self::$commands;
    }

    /**
     * 获取所有分类
     *
     * @return array
     */
    public static function getCategories(): array
    {
        return self::$categories;
    }

    /**
     * 检查用户权限
     *
     * @param string $access 权限级别
     * @return bool
     */
    public static function checkAccess(string $access): bool
    {
        $user = User::alloc();
        return $user->pass($access, true);
    }

    /**
     * 获取过滤后的命令（根据权限）
     *
     * @return array
     */
    public static function getFilteredCommands(): array
    {
        $filtered = [];
        foreach (self::$commands as $id => $command) {
            if (!empty($command['hidden'])) {
                continue;
            }
            if (!self::checkAccess($command['access'])) {
                continue;
            }
            $filtered[$id] = $command;
        }
        return $filtered;
    }

    /**
     * 初始化内置命令
     *
     * @return void
     */
    public static function initDefaultCommands(): void
    {
        if (self::$initialized) {
            return;
        }

        $options = \Widget\Options::alloc();
        $adminUrl = $options->adminUrl;
        $siteUrl = $options->siteUrl;
        $logoutUrl = $options->logoutUrl;

        $commands = [
            [
                'id' => 'theme-light',
                'title' => _t('切换浅色主题'),
                'category' => 'appearance',
                'icon' => 'i-sun',
                'shortcut' => 'T L',
                'keywords' => [_t('主题'), _t('外观'), _t('亮色'), 'light', 'theme'],
                'action' => 'theme:light'
            ],
            [
                'id' => 'theme-dark',
                'title' => _t('切换深色主题'),
                'category' => 'appearance',
                'icon' => 'i-moon',
                'shortcut' => 'T D',
                'keywords' => [_t('主题'), _t('外观'), _t('暗色'), 'dark', 'theme'],
                'action' => 'theme:dark'
            ],
            [
                'id' => 'theme-system',
                'title' => _t('跟随系统主题'),
                'category' => 'appearance',
                'icon' => 'i-monitor',
                'shortcut' => 'T S',
                'keywords' => [_t('主题'), _t('外观'), _t('自动'), 'system', 'theme'],
                'action' => 'theme:system'
            ],
            [
                'id' => 'sidebar-toggle',
                'title' => _t('切换侧边栏'),
                'category' => 'interface',
                'icon' => 'i-sidebar',
                'shortcut' => 'Ctrl B',
                'keywords' => [_t('侧边栏'), 'sidebar', _t('折叠'), _t('展开')],
                'action' => 'sidebar:toggle'
            ],
            [
                'id' => 'fullscreen-toggle',
                'title' => _t('切换全屏模式'),
                'category' => 'interface',
                'icon' => 'i-maximize',
                'shortcut' => 'Ctrl Shift F',
                'keywords' => [_t('全屏'), 'fullscreen', _t('最大化')],
                'action' => 'fullscreen:toggle'
            ],
            [
                'id' => 'scroll-top',
                'title' => _t('回到页面顶部'),
                'category' => 'interface',
                'icon' => 'i-arrow-up',
                'shortcut' => 'Ctrl Home',
                'keywords' => [_t('顶部'), _t('滚动'), 'scroll', 'top'],
                'action' => 'scroll:top'
            ],
            [
                'id' => 'refresh-page',
                'title' => _t('刷新当前页面'),
                'category' => 'interface',
                'icon' => 'i-refresh',
                'shortcut' => 'Ctrl R',
                'keywords' => [_t('刷新'), 'reload', 'refresh'],
                'action' => 'page:refresh'
            ],
            [
                'id' => 'new-post',
                'title' => _t('撰写新文章'),
                'category' => 'create',
                'icon' => 'i-pencil',
                'shortcut' => 'N P',
                'keywords' => [_t('文章'), _t('撰写'), _t('新建'), 'post', 'write'],
                'access' => 'contributor',
                'url' => $adminUrl . 'write-post.php'
            ],
            [
                'id' => 'new-page',
                'title' => _t('创建独立页面'),
                'category' => 'create',
                'icon' => 'i-file',
                'shortcut' => 'N G',
                'keywords' => [_t('页面'), _t('创建'), _t('新建'), 'page'],
                'access' => 'editor',
                'url' => $adminUrl . 'write-page.php'
            ],
            [
                'id' => 'new-category',
                'title' => _t('新建分类'),
                'category' => 'create',
                'icon' => 'i-folder',
                'shortcut' => 'N C',
                'keywords' => [_t('分类'), _t('新建'), 'category'],
                'access' => 'editor',
                'url' => $adminUrl . 'category.php'
            ],
            [
                'id' => 'new-user',
                'title' => _t('新建用户'),
                'category' => 'create',
                'icon' => 'i-user-plus',
                'shortcut' => 'N U',
                'keywords' => [_t('用户'), _t('新建'), 'user'],
                'access' => 'administrator',
                'url' => $adminUrl . 'user.php'
            ],
            [
                'id' => 'upload-media',
                'title' => _t('上传媒体文件'),
                'category' => 'create',
                'icon' => 'i-upload',
                'shortcut' => 'N M',
                'keywords' => [_t('上传'), _t('媒体'), _t('文件'), 'upload', 'media'],
                'access' => 'editor',
                'url' => $adminUrl . 'manage-medias.php'
            ],
            [
                'id' => 'manage-posts',
                'title' => _t('管理文章'),
                'category' => 'manage',
                'icon' => 'i-file-text',
                'shortcut' => 'M P',
                'keywords' => [_t('文章'), _t('管理'), 'posts'],
                'access' => 'contributor',
                'url' => $adminUrl . 'manage-posts.php'
            ],
            [
                'id' => 'manage-pages',
                'title' => _t('管理独立页面'),
                'category' => 'manage',
                'icon' => 'i-files',
                'shortcut' => 'M G',
                'keywords' => [_t('页面'), _t('管理'), 'pages'],
                'access' => 'editor',
                'url' => $adminUrl . 'manage-pages.php'
            ],
            [
                'id' => 'manage-comments',
                'title' => _t('管理评论'),
                'category' => 'manage',
                'icon' => 'i-message',
                'shortcut' => 'M C',
                'keywords' => [_t('评论'), _t('管理'), 'comments'],
                'access' => 'contributor',
                'url' => $adminUrl . 'manage-comments.php'
            ],
            [
                'id' => 'manage-comments-waiting',
                'title' => _t('待审核评论'),
                'category' => 'manage',
                'icon' => 'i-inbox',
                'shortcut' => 'M W',
                'keywords' => [_t('评论'), _t('待审核'), 'waiting', 'pending'],
                'access' => 'contributor',
                'url' => $adminUrl . 'manage-comments.php?status=waiting'
            ],
            [
                'id' => 'manage-categories',
                'title' => _t('管理分类'),
                'category' => 'manage',
                'icon' => 'i-folder',
                'shortcut' => 'M T',
                'keywords' => [_t('分类'), _t('管理'), 'categories'],
                'access' => 'editor',
                'url' => $adminUrl . 'manage-categories.php'
            ],
            [
                'id' => 'manage-tags',
                'title' => _t('管理标签'),
                'category' => 'manage',
                'icon' => 'i-tag',
                'shortcut' => 'M A',
                'keywords' => [_t('标签'), _t('管理'), 'tags'],
                'access' => 'editor',
                'url' => $adminUrl . 'manage-tags.php'
            ],
            [
                'id' => 'manage-medias',
                'title' => _t('管理媒体文件'),
                'category' => 'manage',
                'icon' => 'i-image',
                'shortcut' => 'M M',
                'keywords' => [_t('媒体'), _t('文件'), _t('附件'), 'medias'],
                'access' => 'editor',
                'url' => $adminUrl . 'manage-medias.php'
            ],
            [
                'id' => 'manage-users',
                'title' => _t('管理用户'),
                'category' => 'manage',
                'icon' => 'i-users',
                'shortcut' => 'M U',
                'keywords' => [_t('用户'), _t('管理'), 'users'],
                'access' => 'administrator',
                'url' => $adminUrl . 'manage-users.php'
            ],
            [
                'id' => 'settings-general',
                'title' => _t('基本设置'),
                'category' => 'settings',
                'icon' => 'i-gear',
                'shortcut' => 'S G',
                'keywords' => [_t('设置'), _t('基本'), 'general', 'options'],
                'access' => 'administrator',
                'url' => $adminUrl . 'options-general.php'
            ],
            [
                'id' => 'settings-reading',
                'title' => _t('阅读设置'),
                'category' => 'settings',
                'icon' => 'i-book',
                'shortcut' => 'S R',
                'keywords' => [_t('设置'), _t('阅读'), 'reading'],
                'access' => 'administrator',
                'url' => $adminUrl . 'options-reading.php'
            ],
            [
                'id' => 'settings-discussion',
                'title' => _t('评论设置'),
                'category' => 'settings',
                'icon' => 'i-message',
                'shortcut' => 'S D',
                'keywords' => [_t('设置'), _t('评论'), 'discussion'],
                'access' => 'administrator',
                'url' => $adminUrl . 'options-discussion.php'
            ],
            [
                'id' => 'settings-permalink',
                'title' => _t('永久链接设置'),
                'category' => 'settings',
                'icon' => 'i-link',
                'shortcut' => 'S P',
                'keywords' => [_t('设置'), _t('链接'), 'permalink', 'url'],
                'access' => 'administrator',
                'url' => $adminUrl . 'options-permalink.php'
            ],
            [
                'id' => 'settings-mail',
                'title' => _t('邮件设置'),
                'category' => 'settings',
                'icon' => 'i-mail',
                'shortcut' => 'S M',
                'keywords' => [_t('设置'), _t('邮件'), 'mail', 'email', 'smtp'],
                'access' => 'administrator',
                'url' => $adminUrl . 'options-mail.php'
            ],
            [
                'id' => 'settings-cache',
                'title' => _t('缓存设置'),
                'category' => 'settings',
                'icon' => 'i-database',
                'shortcut' => 'S C',
                'keywords' => [_t('设置'), _t('缓存'), 'cache', 'redis', 'apcu'],
                'access' => 'administrator',
                'url' => $adminUrl . 'options-cache.php'
            ],
            [
                'id' => 'plugins',
                'title' => _t('插件管理'),
                'category' => 'settings',
                'icon' => 'i-puzzle',
                'shortcut' => 'P',
                'keywords' => [_t('插件'), 'plugins', _t('扩展')],
                'access' => 'administrator',
                'url' => $adminUrl . 'plugins.php'
            ],
            [
                'id' => 'themes',
                'title' => _t('主题管理'),
                'category' => 'settings',
                'icon' => 'i-palette',
                'shortcut' => 'T',
                'keywords' => [_t('主题'), 'themes', _t('外观')],
                'access' => 'administrator',
                'url' => $adminUrl . 'themes.php'
            ],
            [
                'id' => 'theme-editor',
                'title' => _t('主题编辑器'),
                'category' => 'settings',
                'icon' => 'i-code',
                'shortcut' => 'T E',
                'keywords' => [_t('主题'), _t('编辑器'), 'editor', 'code'],
                'access' => 'administrator',
                'url' => $adminUrl . 'theme-editor.php'
            ],
            [
                'id' => 'backup',
                'title' => _t('数据备份'),
                'category' => 'tools',
                'icon' => 'i-download',
                'shortcut' => 'B',
                'keywords' => [_t('备份'), 'backup', _t('导出')],
                'access' => 'administrator',
                'url' => $adminUrl . 'backup.php'
            ],
            [
                'id' => 'profile',
                'title' => _t('个人资料设置'),
                'category' => 'settings',
                'icon' => 'i-user',
                'shortcut' => 'P R',
                'keywords' => [_t('个人'), _t('资料'), 'profile', _t('设置')],
                'url' => $adminUrl . 'profile.php'
            ],
            [
                'id' => 'dashboard',
                'title' => _t('返回仪表盘'),
                'category' => 'nav',
                'icon' => 'i-home',
                'shortcut' => 'G H',
                'keywords' => [_t('仪表盘'), _t('首页'), 'dashboard', 'home'],
                'url' => $adminUrl . 'index.php'
            ],
            [
                'id' => 'view-site',
                'title' => _t('查看网站首页'),
                'category' => 'nav',
                'icon' => 'i-globe',
                'shortcut' => 'G S',
                'keywords' => [_t('网站'), _t('首页'), 'visit', 'site'],
                'url' => $siteUrl,
                'target' => '_blank'
            ],
            [
                'id' => 'logout',
                'title' => _t('退出登录'),
                'category' => 'nav',
                'icon' => 'i-log-out',
                'shortcut' => 'L',
                'keywords' => [_t('退出'), _t('登出'), 'logout', 'signout'],
                'url' => $logoutUrl
            ],
            [
                'id' => 'clear-cache',
                'title' => _t('清除前端缓存'),
                'category' => 'tools',
                'icon' => 'i-trash',
                'shortcut' => 'C C',
                'keywords' => [_t('清除'), _t('缓存'), 'cache', 'clear'],
                'confirm' => _t('确定要清除前端缓存吗？这将重置您的界面偏好设置。'),
                'action' => 'cache:clear'
            ]
        ];

        self::registerAll($commands);
        self::$initialized = true;
    }

    /**
     * 输出命令面板所需的 JavaScript 配置
     *
     * @return void
     */
    public static function outputConfig(): void
    {
        self::initDefaultCommands();

        $options = \Widget\Options::alloc();
        $iconsUrl = $options->adminStaticUrl('img', 'icons.svg', true);

        $config = [
            'iconsUrl' => $iconsUrl,
            'categories' => self::$categories,
            'commands' => self::getFilteredCommands()
        ];

        echo '<script>window.__trPaletteConfig = ' . json_encode($config, JSON_UNESCAPED_UNICODE) . ';</script>';
    }
}
