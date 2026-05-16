<?php

namespace Widget;

use Typecho\Common;
use Typecho\Config;
use Typecho\Db;
use Typecho\Plugin\Exception as PluginException;
use Typecho\Date;
use Typecho\Router;
use Typecho\Router\Parser;
use Utils\Zone;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Options extends Base
{
    private array $pluginConfig = [];

    private array $personalPluginConfig = [];

    protected function initComponents(int &$components)
    {
        $components = self::INIT_NONE;
    }

    protected function initParameter(Config $parameter)
    {
        if (!$parameter->isEmpty()) {
            $this->row = $this->parameter->toArray();
        } else {
            $this->db = Db::get();
        }
    }

    public function execute()
    {
        $options = [];

        if (isset($this->db)) {
            $values = $this->db->fetchAll($this->db->select()->from('table.options')
                ->where('user = 0'));

            if (empty($values)) {
                $this->response->redirect(defined('__TYPECHO_ADMIN__')
                    ? '../install.php?step=3' : 'install.php?step=3');
            }

            $options = array_column($values, 'value', 'name');

            $theme = isset($options['theme']) && is_string($options['theme']) && trim($options['theme']) !== ''
                ? $options['theme']
                : 'default';
            $options['theme'] = $theme;

            $themeOptionsKey = 'theme:' . $theme;
            if (array_key_exists($themeOptionsKey, $options)) {
                $themeOptions = $this->decodeArrayOption($themeOptionsKey, $options[$themeOptionsKey], [], true);
                $options = array_merge($options, $themeOptions);
            }
        } elseif (!empty($this->row)) {
            $options = \Utils\Defaults::bootstrapOptions($this->row);
        }

        $this->push($options);
    }

    public function themeFile(string $theme, string $file = ''): string
    {
        return __TYPECHO_ROOT_DIR__ . __TYPECHO_THEME_DIR__ . '/' . trim($theme, './') . '/' . trim($file, './');
    }

    public function siteUrl(?string $path = null)
    {
        echo Common::url($path, $this->siteUrl);
    }

    public function index(?string $path = null)
    {
        echo Common::url($path, $this->index);
    }

    /**
     * 输出模板路径
     *
     * @param string|null $path 子路径
     * @param string|null $theme 模版名称
     * @return string | void
     */
    public function themeUrl(?string $path = null, ?string $theme = null)
    {
        if (!isset($theme)) {
            echo Common::url($path, $this->themeUrl);
        } else {
            $url = defined('__TYPECHO_THEME_URL__') ? __TYPECHO_THEME_URL__ :
                Common::url(__TYPECHO_THEME_DIR__ . '/' . $theme, $this->siteUrl);

            return isset($path) ? Common::url($path, $url) : $url;
        }
    }

    public function pluginUrl(?string $path = null)
    {
        echo Common::url($path, $this->pluginUrl);
    }

    public function pluginDir(?string $plugin = null): string
    {
        return Common::url($plugin, $this->pluginDir);
    }

    /**
     * 输出后台路径
     *
     * @param string|null $path 子路径
     * @param bool $return
     * @return void|string
     */
    public function adminUrl(?string $path = null, bool $return = false)
    {
        $url = Common::url($path, $this->adminUrl);

        if ($return) {
            return $url;
        }

        echo $url;
    }

    /**
     * 获取或输出后台静态文件路径
     *
     * @param string $type
     * @param string|null $file
     * @param bool $return
     * @return void|string
     */
    public function adminStaticUrl(string $type, ?string $file = null, bool $return = false)
    {
        $url = Common::url($type, $this->adminUrl);

        if (empty($file)) {
            return $url;
        }

        $url = Common::url($file, $url) . '?v=' . $this->version;

        if ($return) {
            return $url;
        }

        echo $url;
    }

    public function commentsHTMLTagAllowed()
    {
        echo htmlspecialchars((string) ($this->commentsHTMLTagAllowed ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 获取插件系统参数
     *
     * @param mixed $pluginName 插件名称
     * @return mixed
     */
    public function plugin($pluginName)
    {
        if (!isset($this->pluginConfig[$pluginName])) {
            $name = 'plugin:' . $pluginName;
            if (array_key_exists($name, $this->row)) {
                $options = $this->decodeArrayOption($name, $this->row[$name], [], true);
                $this->pluginConfig[$pluginName] = new Config($options);
            } else {
                throw new PluginException(_t('插件%s的配置信息没有找到', $pluginName), 500);
            }
        }

        return $this->pluginConfig[$pluginName];
    }

    /**
     * 获取个人插件系统参数
     *
     * @param mixed $pluginName 插件名称
     *
     * @return mixed
     */
    public function personalPlugin($pluginName)
    {
        if (!isset($this->personalPluginConfig[$pluginName])) {
            $name = '_plugin:' . $pluginName;
            if (array_key_exists($name, $this->row)) {
                $options = $this->decodeArrayOption($name, $this->row[$name], [], true);
                $this->personalPluginConfig[$pluginName] = new Config($options);
            } else {
                throw new PluginException(_t('插件%s的配置信息没有找到', $pluginName), 500);
            }
        }

        return $this->personalPluginConfig[$pluginName];
    }

    protected function ___routingTable(): array
    {
        $routingTable = $this->decodeArrayOption('routingTable', $this->row['routingTable'] ?? null, \Utils\Defaults::routingTable(), true);

        if (isset($this->db) && !isset($routingTable[0])) {
            $parser = new Parser($routingTable);
            $parsedRoutingTable = $parser->parse();
            $routingTable = array_merge([$parsedRoutingTable], $routingTable);
            $this->repairArrayOption('routingTable', $routingTable);
        }

        return $routingTable;
    }

    protected function ___actionTable(): array
    {
        return $this->decodeArrayOption('actionTable', $this->row['actionTable'] ?? null, [], true);
    }

    protected function ___panelTable(): array
    {
        return $this->decodeArrayOption('panelTable', $this->row['panelTable'] ?? null, [], true);
    }

    protected function ___plugins(): array
    {
        return $this->decodeArrayOption('plugins', $this->row['plugins'] ?? null, [], true);
    }

    protected function ___missingTheme(): ?string
    {
        return !is_dir($this->themeFile($this->row['theme'])) ? $this->row['theme'] : null;
    }

    protected function ___theme(): string
    {
        return $this->missingTheme ? 'default' : $this->row['theme'];
    }

    protected function ___rootUrl(): string
    {
        $rootUrl = defined('__TYPECHO_ROOT_URL__') ? __TYPECHO_ROOT_URL__ : $this->request->getRequestRoot();

        if (defined('__TYPECHO_ADMIN__')) {
            $adminDir = '/' . trim(defined('__TYPECHO_ADMIN_DIR__') ? __TYPECHO_ADMIN_DIR__ : '/admin/', '/');
            $rootUrl = substr($rootUrl, 0, - strlen($adminDir));
        }

        return $rootUrl;
    }

    protected function ___originalSiteUrl(): string
    {
        $siteUrl = $this->row['siteUrl'];

        if (defined('__TYPECHO_SITE_URL__')) {
            $siteUrl = __TYPECHO_SITE_URL__;
        } elseif (defined('__TYPECHO_DYNAMIC_SITE_URL__') && __TYPECHO_DYNAMIC_SITE_URL__) {
            $siteUrl = $this->rootUrl;
        }

        return $siteUrl;
    }

    protected function ___siteUrl(): string
    {
        $siteUrl = Common::url(null, $this->originalSiteUrl);

        if ($this->request->isSecure() && 0 === strpos($siteUrl, 'http://')) {
            $siteUrl = substr_replace($siteUrl, 'https', 0, 4);
        }

        return $siteUrl;
    }

    protected function ___siteDomain(): string
    {
        return (string) (parse_url($this->siteUrl, PHP_URL_HOST) ?: '');
    }

    protected function ___feedUrl(): string
    {
        return Router::url('feed', ['feed' => '/'], $this->index);
    }

    protected function ___feedRssUrl(): string
    {
        return Router::url('feed', ['feed' => '/rss/'], $this->index);
    }

    protected function ___feedAtomUrl(): string
    {
        return Router::url('feed', ['feed' => '/atom/'], $this->index);
    }

    protected function ___commentsFeedUrl(): string
    {
        return Router::url('feed', ['feed' => '/comments/'], $this->index);
    }

    protected function ___commentsFeedRssUrl(): string
    {
        return Router::url('feed', ['feed' => '/rss/comments/'], $this->index);
    }

    protected function ___commentsFeedAtomUrl(): string
    {
        return Router::url('feed', ['feed' => '/atom/comments/'], $this->index);
    }

    protected function ___xmlRpcUrl(): string
    {
        return Router::url('do', ['action' => 'xmlrpc'], $this->index);
    }

    protected function ___index(): string
    {
        return ($this->rewrite || (defined('__TYPECHO_REWRITE__') && __TYPECHO_REWRITE__))
            ? $this->rootUrl : Common::url('index.php', $this->rootUrl);
    }

    protected function ___themeUrl(): string
    {
        return $this->themeUrl(null, $this->theme);
    }

    protected function ___pluginUrl(): string
    {
        return defined('__TYPECHO_PLUGIN_URL__') ? __TYPECHO_PLUGIN_URL__ :
            Common::url(__TYPECHO_PLUGIN_DIR__, $this->siteUrl);
    }

    protected function ___pluginDir(): string
    {
        return Common::url(__TYPECHO_PLUGIN_DIR__, __TYPECHO_ROOT_DIR__);
    }

    protected function ___adminUrl(): string
    {
        return Common::url(defined('__TYPECHO_ADMIN_DIR__') ?
            __TYPECHO_ADMIN_DIR__ : '/admin/', $this->rootUrl);
    }

    protected function ___loginUrl(): string
    {
        return Common::url('login.php', $this->adminUrl);
    }

    protected function ___loginAction(): string
    {
        return Security::alloc()->getTokenUrl(
            Router::url(
                'do',
                ['action' => 'login', 'widget' => 'Login'],
                Common::url('index.php', $this->rootUrl)
            )
        );
    }

    protected function ___registerUrl(): string
    {
        return Common::url('register.php', $this->adminUrl);
    }

    protected function ___registerAction(): string
    {
        return Security::alloc()->getTokenUrl(
            Router::url('do', ['action' => 'register', 'widget' => 'Register'], $this->index)
        );
    }

    protected function ___profileUrl(): string
    {
        return Common::url('profile.php', $this->adminUrl);
    }

    protected function ___logoutUrl(): string
    {
        return Security::alloc()->getTokenUrl(
            Common::url('/action/logout', $this->index)
        );
    }

    protected function ___serverTimezone(): int
    {
        return Zone::serverOffsetAt(Date::time());
    }

    protected function ___timezone(): int
    {
        return $this->getTimezoneOffset();
    }

    protected function ___timezoneId(): ?string
    {
        $timezoneId = $this->row['timezoneId'] ?? null;
        if (is_scalar($timezoneId)) {
            $normalized = Zone::normalizeId((string) $timezoneId);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return Zone::legacyId((int) ($this->row['timezone'] ?? 0));
    }

    protected function ___time(): int
    {
        return Date::time();
    }

    public function getTimezoneId(): string
    {
        return (string) $this->timezoneId;
    }

    public function getStoredTimezoneId(): ?string
    {
        $timezoneId = $this->row['timezoneId'] ?? null;
        return is_scalar($timezoneId) ? Zone::normalizeStoredId((string) $timezoneId) : null;
    }

    public function getTimezoneZone(): \DateTimeZone
    {
        return Zone::zone($this->getTimezoneId(), (int) ($this->row['timezone'] ?? 0));
    }

    public function getTimezoneOffset(?int $timestamp = null): int
    {
        return Zone::offsetAt($this->getTimezoneId(), (int) ($this->row['timezone'] ?? 0), $timestamp);
    }

    public function getDateTime(?int $timestamp = null): \DateTimeImmutable
    {
        return Zone::dateTime($timestamp, $this->getTimezoneId(), (int) ($this->row['timezone'] ?? 0));
    }

    public function getUtcDateTime(?int $timestamp = null): \DateTimeImmutable
    {
        return Zone::utcDateTime($timestamp);
    }

    public function formatDateTime(?int $timestamp, string $format): string
    {
        return $this->getDateTime($timestamp)->format($format);
    }

    public function parseDateTime(string $date, string $time = '00:00:00'): ?int
    {
        return Zone::fromString($date, $time, $this->getTimezoneId(), (int) ($this->row['timezone'] ?? 0));
    }

    public function makeDateTime(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0
    ): ?int {
        return Zone::fromParts(
            $year,
            $month,
            $day,
            $hour,
            $minute,
            $second,
            $this->getTimezoneId(),
            (int) ($this->row['timezone'] ?? 0)
        );
    }

    public function getRange(int $year, ?int $month = null, ?int $day = null): array
    {
        return Zone::range($year, $month, $day, $this->getTimezoneId(), (int) ($this->row['timezone'] ?? 0));
    }

    protected function ___contentType(): string
    {
        return (string) ($this->row['contentType'] ?? 'text/html');
    }

    protected function ___software(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->generator), 2) ?: [];
        return (string) ($parts[0] ?? '');
    }

    protected function ___version(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->generator), 2) ?: [];
        $version = (string) ($parts[1] ?? '');
        $pos = strpos($version, '/');

        if ($pos !== false) {
            $version = substr($version, 0, $pos) . '.0';
        }

        return $version;
    }

    protected function ___allowedAttachmentTypes(): array
    {
        $attachmentTypesResult = [];

        if (null != $this->attachmentTypes) {
            $attachmentTypes = str_replace(
                ['@image@', '@media@', '@doc@'],
                [
                    'gif,jpg,jpeg,png,tiff,bmp,webp,avif', 'mp3,mp4,mov,wmv,wma,rmvb,rm,avi,flv,ogg,oga,ogv',
                    'txt,doc,docx,xls,xlsx,ppt,pptx,zip,rar,pdf'
                ],
                $this->attachmentTypes
            );

            $attachmentTypesResult = array_values(array_unique(array_filter(
                array_map('trim', explode(',', $attachmentTypes)),
                static fn(string $type): bool => $type !== '' && !in_array($type, ['html', 'htm'], true)
            )));
        }

        return $attachmentTypesResult;
    }

    private function decodeArrayOption(string $name, mixed $rawValue, array $fallback = [], bool $repairInvalid = false): array
    {
        $decoded = is_array($rawValue)
            ? $rawValue
            : (is_string($rawValue) ? $this->tryDeserialize($rawValue) : null);

        if (is_array($decoded)) {
            return $decoded;
        }

        if ($repairInvalid) {
            $this->repairArrayOption($name, $fallback);
        }

        return $fallback;
    }

    private function repairArrayOption(string $name, array $value): void
    {
        if (!isset($this->db)) {
            return;
        }

        try {
            $this->saveOption($name, $value);
            $encoded = Common::jsonEncode($value, 0, '');
            if (is_string($encoded)) {
                $this->row[$name] = $encoded;
            }
        } catch (\Throwable) {
        }
    }

    private function tryDeserialize(string $value)
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $isSerialized = preg_match('/^(a|O|s|i|d|b|N):/', $value) === 1;
        if ($isSerialized) {
            set_error_handler(static function (): bool {
                return true;
            });

            try {
                $result = unserialize($value, ['allowed_classes' => false]);
            } finally {
                restore_error_handler();
            }

            if ($result === false && $value !== 'b:0;' && $value !== 'N;') {
                return null;
            }
            return $result;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
