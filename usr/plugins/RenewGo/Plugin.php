<?php
/**
 * 【TypeRenew 专用】外链安全拓展
 *
 * @package RenewGo
 * @author TypeRenew
 * @link https://github.com/Yangsh888/TypeRenew
 * @version 1.0.0
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use Typecho\Cache;
use Typecho\Common;
use Typecho\Db;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Utils\Helper;
use Utils\NoPersonal;
use Utils\Pref;

require_once __DIR__ . '/Action.php';

class RenewGo_Plugin implements PluginInterface
{
    use NoPersonal;

    private const NAME = 'RenewGo';
    private const CACHE_KEY = 'renewgo:settings:v1';
    private const RULE_CACHE_KEY = 'renewgo:rules:v1';
    private const SIGN_WINDOW = 900;
    private const MODE_INTERSTITIAL = 'interstitial';
    private const MODE_DIRECT = 'direct302';
    private const MODE_OFF = 'off';

    private static array $runtimeRules = [];
    private static bool $buffering = false;
    private static int $bufferLevel = 0;

    public static function activate()
    {
        self::createTables();
        self::registerHooks();
        self::registerRoutes();
        self::registerPanel();
        self::ensureConfigStored();
        self::clearConfigCache();
        return _t('RenewGo 已启用');
    }

    public static function deactivate()
    {
        self::unregisterRoutes();
        self::unregisterPanel();
        self::clearConfigCache();
    }

    public static function config(Form $form)
    {
        $defaults = self::defaults();

        $enabled = new Form\Element\Radio(
            'enabled',
            ['1' => _t('启用插件'), '0' => _t('停用插件')],
            (string) $defaults['enabled'],
            _t('插件状态')
        );
        $form->addInput($enabled);

        $mode = new Form\Element\Select(
            'mode',
            [
                self::MODE_INTERSTITIAL => _t('安全提示页'),
                self::MODE_DIRECT => _t('直接 302 跳转'),
                self::MODE_OFF => _t('关闭改写')
            ],
            (string) $defaults['mode'],
            _t('跳转模式')
        );
        $form->addInput($mode);

        $rewrite = new Form\Element\Checkbox(
            'rewrite',
            [
                'content' => _t('改写文章正文外链'),
                'comments' => _t('改写评论正文外链'),
                'author' => _t('改写评论作者链接'),
                'client' => _t('启用前端动态改写（可选）'),
                'fallback' => _t('启用渲染后兜底改写（可选）')
            ],
            (array) $defaults['rewrite'],
            _t('改写范围')
        );
        $form->addInput($rewrite->multiMode());
        $form->addInput(new Form\Element\Hidden('rewriteContent', null, ''));
        $form->addInput(new Form\Element\Hidden('rewriteComments', null, ''));
        $form->addInput(new Form\Element\Hidden('rewriteAuthor', null, ''));
        $form->addInput(new Form\Element\Hidden('rewriteClient', null, ''));
        $form->addInput(new Form\Element\Hidden('rewriteFallback', null, ''));

        $rel = new Form\Element\Checkbox(
            'rel',
            [
                'nofollow' => 'nofollow',
                'noopener' => 'noopener',
                'noreferrer' => 'noreferrer'
            ],
            (array) $defaults['rel'],
            _t('外链 rel 策略')
        );
        $form->addInput($rel->multiMode());

        $newTab = new Form\Element\Radio(
            'openInNewTab',
            ['1' => _t('新窗口打开'), '0' => _t('当前窗口打开')],
            (string) $defaults['openInNewTab'],
            _t('跳转打开方式')
        );
        $form->addInput($newTab);

        $directWhitelistOnly = new Form\Element\Radio(
            'directWhitelistOnly',
            ['1' => _t('直跳仅允许白名单'), '0' => _t('直跳允许全部外链')],
            (string) $defaults['directWhitelistOnly'],
            _t('直跳安全限制')
        );
        $form->addInput($directWhitelistOnly);

        $title = new Form\Element\Text(
            'pageTitle',
            null,
            (string) $defaults['pageTitle'],
            _t('提示页标题')
        );
        $title->input->setAttribute('class', 'w-100');
        $form->addInput($title);

        $seconds = new Form\Element\Number(
            'staySeconds',
            null,
            (int) $defaults['staySeconds'],
            _t('提示页自动跳转秒数'),
            _t('0 表示不自动跳转')
        );
        $seconds->input->setAttribute('class', 'w-20');
        $form->addInput($seconds->addRule('isInteger', _t('请填入一个数字')));

        $limit = new Form\Element\Number(
            'maxJumpPerHour',
            null,
            (int) $defaults['maxJumpPerHour'],
            _t('每小时每 IP 最大跳转次数')
        );
        $limit->input->setAttribute('class', 'w-20');
        $form->addInput($limit->addRule('isInteger', _t('请填入一个数字')));

        $logLevel = new Form\Element\Select(
            'logLevel',
            ['off' => _t('关闭日志'), 'basic' => _t('基础日志'), 'full' => _t('完整日志')],
            (string) $defaults['logLevel'],
            _t('日志级别')
        );
        $form->addInput($logLevel);

            $signWindow = new Form\Element\Number(
            'signWindow',
            null,
            (int) $defaults['signWindow'],
            _t('跳转签名有效秒数')
        );
        $signWindow->input->setAttribute('class', 'w-20');
        $form->addInput($signWindow->addRule('isInteger', _t('请填入一个数字')));

        $logKeepDays = new Form\Element\Number(
            'logKeepDays',
            null,
            (int) $defaults['logKeepDays'],
            _t('日志保留天数'),
            _t('超过此天数的日志将被自动清理，0 表示不清理')
        );
        $logKeepDays->input->setAttribute('class', 'w-20');
        $form->addInput($logKeepDays->addRule('isInteger', _t('请填入一个数字')));

        $ttl = new Form\Element\Number(
            'cacheTtl',
            null,
            (int) $defaults['cacheTtl'],
            _t('配置缓存秒数')
        );
        $ttl->input->setAttribute('class', 'w-20');
        $form->addInput($ttl->addRule('isInteger', _t('请填入一个数字')));

        $size = new Form\Element\Number(
            'panelSize',
            null,
            (int) $defaults['panelSize'],
            _t('后台日志每页数量')
        );
        $size->input->setAttribute('class', 'w-20');
        $form->addInput($size->addRule('isInteger', _t('请填入一个数字')));

        $whitelist = new Form\Element\Textarea(
            'whitelist',
            null,
            (string) $defaults['whitelist'],
            _t('外链白名单'),
            _t("每行一条，支持 example.com、*.example.com、example.com/path、https://example.com/path")
        );
        $whitelist->input->setAttribute('class', 'w-100 mono');
        $form->addInput($whitelist);
    }

    public static function configHandle(array &$settings, bool $isInit)
    {
        unset($isInit);
        $settings = self::normalize($settings);
        \Widget\Plugins\Edit::configPlugin(self::NAME, $settings);
        self::clearConfigCache();
    }

    public static function registerHooks(): void
    {
        \Typecho\Plugin::factory('Widget\\Base\\Contents')->contentEx = ['RenewGo_Plugin', 'rewriteContent'];
        \Typecho\Plugin::factory('Widget\\Base\\Contents')->excerptEx = ['RenewGo_Plugin', 'rewriteContent'];
        \Typecho\Plugin::factory('Widget\\Base\\Comments')->contentEx = ['RenewGo_Plugin', 'rewriteComments'];
        \Typecho\Plugin::factory('Widget\\Base\\Comments')->content = ['RenewGo_Plugin', 'rewriteComments'];
        \Typecho\Plugin::factory('Widget\\Base\\Comments')->filter = ['RenewGo_Plugin', 'rewriteAuthorUrl'];
        \Typecho\Plugin::factory('Widget\\Archive')->header = ['RenewGo_Plugin', 'startBuffer'];
        \Typecho\Plugin::factory('Widget\\Archive')->footer = ['RenewGo_Plugin', 'endBuffer'];
    }

    public static function registerRoutes(): void
    {
        Helper::removeRoute('renew_go');
        Helper::removeRoute('renew_go_action');
        Helper::addRoute('renew_go', '/go/[target]', 'RenewGo_Action', 'go');
        Helper::addRoute('renew_go_action', '/action/renew-go', 'RenewGo_Action', 'action');
    }

    public static function unregisterRoutes(): void
    {
        Helper::removeRoute('renew_go');
        Helper::removeRoute('renew_go_action');
    }

    public static function registerPanel(): void
    {
        Helper::removePanel(3, 'RenewGo/Panel.php');
        Helper::addPanel(3, 'RenewGo/Panel.php', '外链安全', '外链安全', 'administrator');
    }

    public static function unregisterPanel(): void
    {
        Helper::removePanel(3, 'RenewGo/Panel.php');
    }

    public static function getSettings(): array
    {
        static $runtime = null;
        return Pref::load(
            $runtime,
            self::CACHE_KEY,
            self::defaults(),
            static fn() => (array) Helper::options()->plugin(self::NAME)->toArray(),
            static fn(array $settings): array => self::normalize($settings),
            static fn() => self::ensureConfigStored(),
            static function (string $scope, Throwable $e): void {
                self::reportException('getSettings.' . $scope, $e);
            }
        );
    }

    public static function apiUrl(string $do): string
    {
        $path = Common::url('action/renew-go?do=' . rawurlencode($do), Helper::options()->index);
        return Helper::security()->getTokenUrl($path, 'renew-go-admin');
    }

    public static function assetUrl(string $path): string
    {
        return Common::url(self::NAME . '/' . ltrim($path, '/'), Helper::options()->pluginUrl);
    }

    public static function buildGoUrl(string $url): string
    {
        $encoded = self::encodeTarget($url);
        return Common::url('go/' . $encoded, Helper::options()->index);
    }

    public static function buildJumpUrl(string $encoded): string
    {
        $time = time();
        $sign = self::sign($encoded, $time);
        $path = 'action/renew-go?do=jump&target=' . rawurlencode($encoded) . '&ts=' . $time . '&sig=' . $sign;
        return Common::url($path, Helper::options()->index);
    }

    public static function sign(string $encoded, int $time): string
    {
        $secret = self::getSigningSecret();
        return hash_hmac('sha256', $encoded . '|' . $time, $secret);
    }

    public static function getSigningSecret(): string
    {
        $options = Helper::options();
        $secret = (string) ($options->secret ?? '');
        if ($secret !== '') {
            return $secret;
        }

        $pluginSecret = (string) ($options->plugin('RenewGo')->signSecret ?? '');
        if ($pluginSecret !== '') {
            return $pluginSecret;
        }

        return self::ensureSigningSecret();
    }

    private static function ensureSigningSecret(): string
    {
        $cache = Cache::getInstance();
        $lockKey = 'renewgo:sign_secret_lock';
        $cacheKey = 'renewgo:sign_secret';

        if ($cache->enabled()) {
            $hit = false;
            $cached = $cache->get($cacheKey, $hit);
            if ($hit && is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $db = Db::get();
        $prefix = $db->getPrefix();

        $lockAcquired = false;
        if ($cache->enabled()) {
            $lockAcquired = $cache->tryLock($lockKey, 5);
        }

        if (!$lockAcquired) {
            usleep(100000);
            for ($i = 0; $i < 10; $i++) {
                $row = $db->fetchRow(
                    $db->select('value')->from($prefix . 'options')
                        ->where('name = ?', 'plugin:RenewGo')
                        ->limit(1)
                );
                if ($row) {
                    $config = @unserialize((string) ($row['value'] ?? ''));
                    if (is_array($config) && !empty($config['signSecret'])) {
                        return (string) $config['signSecret'];
                    }
                }
                usleep(50000);
            }
        }

        try {
            $newSecret = bin2hex(random_bytes(32));

            $existing = $db->fetchRow(
                $db->select('value')->from($prefix . 'options')
                    ->where('name = ?', 'plugin:RenewGo')
                    ->limit(1)
            );

            if ($existing) {
                $config = @unserialize((string) ($existing['value'] ?? ''));
                if (is_array($config) && !empty($config['signSecret'])) {
                    $finalSecret = (string) $config['signSecret'];
                    if ($cache->enabled()) {
                        $cache->set($cacheKey, $finalSecret, 3600);
                    }
                    return $finalSecret;
                }

                $config = is_array($config) ? $config : [];
                $config['signSecret'] = $newSecret;
                $db->query(
                    $db->update($prefix . 'options')
                        ->rows(['value' => serialize($config)])
                        ->where('name = ?', 'plugin:RenewGo')
                );
            } else {
                $config = ['signSecret' => $newSecret];
                $db->query(
                    $db->insert($prefix . 'options')
                        ->rows([
                            'name' => 'plugin:RenewGo',
                            'user' => 0,
                            'value' => serialize($config)
                        ])
                );
            }

            if ($cache->enabled()) {
                $cache->set($cacheKey, $newSecret, 3600);
            }

            return $newSecret;
        } finally {
            if ($lockAcquired && $cache->enabled()) {
                $cache->unlock($lockKey);
            }
        }
    }

    public static function verifySign(string $encoded, int $time, string $sign): bool
    {
        $settings = self::getSettings();
        $window = (int) ($settings['signWindow'] ?? self::SIGN_WINDOW);
        $window = max(60, min(1800, $window));
        if ($time <= 0 || abs(time() - $time) > $window) {
            return false;
        }
        $expected = self::sign($encoded, $time);
        return hash_equals($expected, $sign);
    }

    public static function encodeTarget(string $url): string
    {
        return rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }

    public static function decodeTarget(string $encoded): string
    {
        if (!preg_match('/^[A-Za-z0-9\-_]+$/', $encoded)) {
            return '';
        }
        $b64 = strtr($encoded, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($b64, true);
        return is_string($decoded) ? trim($decoded) : '';
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        if ($url === '' || strlen($url) > 4096) {
            return '';
        }
        if (preg_match('/[\x00-\x1F\x7F]/u', $url)) {
            return '';
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }
        $host = strtolower((string) $parts['host']);
        if ($host === '') {
            return '';
        }
        $normalized = $scheme . '://' . $host;
        if (!empty($parts['port'])) {
            $normalized .= ':' . (int) $parts['port'];
        }
        $normalized .= (string) ($parts['path'] ?? '/');
        if (!empty($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $normalized .= '#' . $parts['fragment'];
        }
        return $normalized;
    }

    public static function rewriteContent($content, $widget = null): string
    {
        unset($widget);
        return self::rewriteHtml((string) $content, 'content');
    }

    public static function rewriteComments($content, $widget = null): string
    {
        unset($widget);
        return self::rewriteHtml((string) $content, 'comments');
    }

    public static function rewriteAuthorUrl($comment)
    {
        if (!is_array($comment)) {
            return $comment;
        }

        $settings = self::getSettings();
        if (!self::isEnabled($settings) || !self::isRewriteEnabled($settings, 'author')) {
            return $comment;
        }

        $url = isset($comment['url']) ? (string) $comment['url'] : '';
        $normalized = self::normalizeUrl($url);
        if (!self::shouldRewriteUrl($normalized, $settings)) {
            return $comment;
        }

        $comment['url'] = self::buildGoUrl($normalized);
        return $comment;
    }

    public static function startBuffer(): void
    {
        $settings = self::getSettings();
        if (!self::isEnabled($settings) || !self::isRewriteEnabled($settings, 'fallback')) {
            return;
        }
        if (self::$buffering) {
            return;
        }
        self::$bufferLevel = ob_get_level();
        ob_start();
        self::$buffering = true;
    }

    public static function endBuffer(): void
    {
        if (!self::$buffering) {
            return;
        }
        if (ob_get_level() <= self::$bufferLevel) {
            self::$buffering = false;
            self::$bufferLevel = 0;
            return;
        }
        $settings = self::getSettings();
        $content = (string) ob_get_contents();
        ob_end_clean();
        self::$buffering = false;
        self::$bufferLevel = 0;
        $content = self::rewriteHtml($content, 'fallback', $settings);
        if (self::isRewriteEnabled($settings, 'client') && self::MODE_OFF !== $settings['mode']) {
            $content = self::injectClientScript($content, $settings);
        }
        echo $content;
    }

    public static function rewriteHtml(string $html, string $scope, ?array $settings = null): string
    {
        $settings = $settings ?? self::getSettings();
        if (!self::isEnabled($settings) || self::MODE_OFF === $settings['mode'] || $html === '') {
            return $html;
        }

        if ($scope === 'content' && !self::isRewriteEnabled($settings, 'content')) {
            return $html;
        }
        if ($scope === 'comments' && !self::isRewriteEnabled($settings, 'comments')) {
            return $html;
        }
        if ($scope === 'fallback' && !self::isRewriteEnabled($settings, 'fallback')) {
            return $html;
        }

        $pattern = '/<a\b[^>]*>/i';
        return (string) preg_replace_callback($pattern, function (array $matches) use ($settings) {
            return self::rewriteAnchorTag($matches[0], $settings);
        }, $html);
    }

    public static function rewriteAnchorTag(string $tag, array $settings): string
    {
        if (stripos($tag, 'data-renewgo-skip=') !== false || stripos($tag, 'data-renewgo="1"') !== false) {
            return $tag;
        }

        [$href, $quote] = self::extractHref($tag);
        if ($href === '') {
            return $tag;
        }

        $normalized = self::normalizeUrl($href);
        if (!self::shouldRewriteUrl($normalized, $settings)) {
            return $tag;
        }

        $go = self::buildGoUrl($normalized);
        $tag = self::replaceHref($tag, $go, $quote);
        $tag = self::mergeRel($tag, (array) $settings['rel']);
        if (!empty($settings['openInNewTab'])) {
            $tag = self::setTarget($tag, '_blank');
        }
        if (stripos($tag, 'data-renewgo=') === false) {
            $tag = rtrim(substr($tag, 0, -1)) . ' data-renewgo="1">';
        }
        return $tag;
    }

    public static function shouldRewriteUrl(string $url, array $settings): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        $siteHost = strtolower((string) parse_url((string) Helper::options()->siteUrl, PHP_URL_HOST));
        if ($siteHost !== '' && $host === $siteHost) {
            return false;
        }

        if (self::isWhitelisted($url, $settings)) {
            return false;
        }

        return true;
    }

    public static function isWhitelisted(string $url, array $settings): bool
    {
        if ($url === '') {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '/');
        $needle = $host . $path;

        $rules = self::parseRules((string) ($settings['whitelist'] ?? ''));
        if (isset($rules['exactHost'][$host])) {
            return true;
        }
        if (isset($rules['exactUrl'][strtolower($url)])) {
            return true;
        }
        foreach ($rules['wildHost'] as $suffix) {
            if ($suffix !== '' && (str_ends_with('.' . $host, '.' . $suffix) || $host === $suffix)) {
                return true;
            }
        }
        foreach ($rules['hostPath'] as $prefix) {
            if ($prefix !== '' && str_starts_with($needle, $prefix)) {
                return true;
            }
        }
        return false;
    }

    public static function parseRules(string $whitelist): array
    {
        $key = md5($whitelist);
        if (isset(self::$runtimeRules[$key])) {
            return self::$runtimeRules[$key];
        }

        $cache = Cache::getInstance();
        if ($cache->enabled()) {
            $hit = false;
            $cached = $cache->get(self::RULE_CACHE_KEY . ':' . $key, $hit);
            if ($hit && is_array($cached)) {
                self::$runtimeRules[$key] = $cached;
                return $cached;
            }
        }

        $rules = [
            'exactUrl' => [],
            'exactHost' => [],
            'wildHost' => [],
            'hostPath' => []
        ];

        $lines = preg_split('/\R/u', $whitelist) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $line = preg_replace('/\s+/', '', $line);
            if ($line === null || $line === '') {
                continue;
            }

            if (str_starts_with($line, 'http://') || str_starts_with($line, 'https://')) {
                $norm = self::normalizeUrl($line);
                if ($norm !== '') {
                    $rules['exactUrl'][strtolower($norm)] = true;
                }
                continue;
            }

            if (str_starts_with($line, '*.')) {
                $suffix = strtolower(substr($line, 2));
                if ($suffix !== '') {
                    $rules['wildHost'][] = $suffix;
                }
                continue;
            }

            if (strpos($line, '/') !== false) {
                $rules['hostPath'][] = strtolower(trim($line, '/'));
                continue;
            }

            $rules['exactHost'][strtolower($line)] = true;
        }

        self::$runtimeRules[$key] = $rules;
        if ($cache->enabled()) {
            $cache->set(self::RULE_CACHE_KEY . ':' . $key, $rules, 600);
        }
        return $rules;
    }

    public static function checkRateLimit(string $ip, array $settings): bool
    {
        $limit = (int) ($settings['maxJumpPerHour'] ?? 120);
        if ($limit <= 0) {
            return true;
        }

        $cache = Cache::getInstance();
        $bucket = date('YmdH');
        $key = 'renewgo:rate:' . md5($ip . ':' . $bucket);
        if ($cache->enabled()) {
            $hit = false;
            $count = (int) $cache->get($key, $hit);
            $count = $hit ? $count : 0;
            if ($count >= $limit) {
                return false;
            }
            $cache->set($key, $count + 1, 3700);
            return true;
        }

        try {
            $db = Db::get();
            $prefix = $db->getPrefix();
            $since = time() - 3600;
            $row = $db->fetchObject($db->select(['COUNT(*)' => 'num'])
                ->from($prefix . 'renew_go_logs')
                ->where('ip = ? AND action = ? AND created_at > ?', $ip, 'jump', $since));
            $count = (int) ($row->num ?? 0);
            if ($count >= $limit) {
                return false;
            }
        } catch (Throwable $e) {
            self::reportException('checkRateLimit', $e);
        }

        return true;
    }

    public static function logEvent(string $action, string $result, string $target = '', string $referer = '', bool $force = false): void
    {
        $settings = self::getSettings();
        $level = (string) ($settings['logLevel'] ?? 'basic');
        if (!$force && $level === 'off') {
            return;
        }

        $ip = (string) \Typecho\Request::getInstance()->getIp();
        $targetHost = (string) parse_url($target, PHP_URL_HOST);
        $targetValue = '';
        $refererValue = '';
        if ($level === 'full') {
            $targetValue = $target;
            $refererValue = $referer;
        } elseif ($level === 'basic' || $force) {
            $targetValue = $targetHost;
            $refererValue = '';
        }

        try {
            $db = Db::get();
            $prefix = $db->getPrefix();
            $db->query($db->insert($prefix . 'renew_go_logs')->rows([
                'ip' => $ip,
                'action' => substr($action, 0, 24),
                'result' => substr($result, 0, 16),
                'target' => mb_substr($targetValue, 0, 512),
                'referer' => mb_substr($refererValue, 0, 512),
                'created_at' => time()
            ]));
        } catch (Throwable $e) {
            self::reportException('logEvent', $e);
        }
    }

    public static function purgeLogs(): int
    {
        $db = Db::get();
        $prefix = $db->getPrefix();
        return $db->query($db->delete($prefix . 'renew_go_logs'));
    }

    public static function cleanupLogs(int $keepDays = 30): int
    {
        $keepDays = max(1, min(365, $keepDays));
        $before = time() - ($keepDays * 86400);
        $db = Db::get();
        $prefix = $db->getPrefix();
        try {
            return (int) $db->query($db->delete($prefix . 'renew_go_logs')->where('created_at < ?', $before));
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function exportRules(): array
    {
        $settings = self::getSettings();
        return [
            'whitelist' => (string) ($settings['whitelist'] ?? ''),
            'mode' => (string) ($settings['mode'] ?? self::MODE_INTERSTITIAL),
            'rewrite' => (array) ($settings['rewrite'] ?? []),
            'rel' => (array) ($settings['rel'] ?? [])
        ];
    }

    public static function importRules(string $rules): array
    {
        $settings = self::getSettings();
        $settings['whitelist'] = $rules;
        $settings = self::normalize($settings);
        \Widget\Plugins\Edit::configPlugin(self::NAME, $settings);
        self::clearConfigCache();
        return $settings;
    }

    private static function defaults(): array
    {
        return [
            'enabled' => '1',
            'mode' => self::MODE_INTERSTITIAL,
            'rewrite' => ['content', 'comments', 'author'],
            'rel' => ['nofollow', 'noopener', 'noreferrer'],
            'openInNewTab' => '1',
            'directWhitelistOnly' => '1',
            'pageTitle' => '外链访问提示',
            'staySeconds' => '0',
            'maxJumpPerHour' => '120',
            'signWindow' => '300',
            'logLevel' => 'basic',
            'logKeepDays' => '30',
            'cacheTtl' => '300',
            'panelSize' => '40',
            'whitelist' => ''
        ];
    }

    private static function normalize(array $settings): array
    {
        $mode = (string) ($settings['mode'] ?? self::MODE_INTERSTITIAL);
        if (!in_array($mode, [self::MODE_INTERSTITIAL, self::MODE_DIRECT, self::MODE_OFF], true)) {
            $mode = self::MODE_INTERSTITIAL;
        }

        $rewriteMap = ['content', 'comments', 'author', 'client', 'fallback'];
        $rewrite = array_values(array_intersect($rewriteMap, (array) ($settings['rewrite'] ?? [])));
        if (empty($rewrite)) {
            if ((string) ($settings['rewriteContent'] ?? '') === '1') {
                $rewrite[] = 'content';
            }
            if ((string) ($settings['rewriteComments'] ?? '') === '1') {
                $rewrite[] = 'comments';
            }
            if ((string) ($settings['rewriteAuthor'] ?? '') === '1') {
                $rewrite[] = 'author';
            }
            if ((string) ($settings['rewriteClient'] ?? '') === '1') {
                $rewrite[] = 'client';
            }
            if ((string) ($settings['rewriteFallback'] ?? '') === '1') {
                $rewrite[] = 'fallback';
            }
            if (empty($rewrite)) {
                $rewrite = ['content', 'comments', 'author'];
            }
        }

        $rel = array_values(array_intersect(
            ['nofollow', 'noopener', 'noreferrer'],
            (array) ($settings['rel'] ?? [])
        ));
        if (empty($rel)) {
            $rel = ['nofollow', 'noopener', 'noreferrer'];
        }

        $logLevel = (string) ($settings['logLevel'] ?? 'basic');
        if (!in_array($logLevel, ['off', 'basic', 'full'], true)) {
            $logLevel = 'basic';
        }

        $title = trim((string) ($settings['pageTitle'] ?? '外链访问提示'));
        if ($title === '') {
            $title = '外链访问提示';
        }

        $whitelist = self::sanitizeWhitelist((string) ($settings['whitelist'] ?? ''));

        return [
            'enabled' => ((string) ($settings['enabled'] ?? '1') === '1') ? '1' : '0',
            'mode' => $mode,
            'rewrite' => $rewrite,
            'rel' => $rel,
            'openInNewTab' => ((string) ($settings['openInNewTab'] ?? '1') === '1') ? '1' : '0',
            'directWhitelistOnly' => ((string) ($settings['directWhitelistOnly'] ?? '1') === '1') ? '1' : '0',
            'pageTitle' => mb_substr($title, 0, 80),
            'staySeconds' => (string) max(0, min(15, (int) ($settings['staySeconds'] ?? 0))),
            'maxJumpPerHour' => (string) max(10, min(5000, (int) ($settings['maxJumpPerHour'] ?? 120))),
            'signWindow' => (string) max(60, min(1800, (int) ($settings['signWindow'] ?? 300))),
            'logLevel' => $logLevel,
            'logKeepDays' => (string) max(0, min(365, (int) ($settings['logKeepDays'] ?? 30))),
            'cacheTtl' => (string) max(60, min(86400, (int) ($settings['cacheTtl'] ?? 300))),
            'panelSize' => (string) max(10, min(200, (int) ($settings['panelSize'] ?? 40))),
            'whitelist' => $whitelist
        ];
    }

    private static function sanitizeWhitelist(string $whitelist): string
    {
        $lines = preg_split('/\R/u', $whitelist) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (strlen($line) > 512) {
                $line = substr($line, 0, 512);
            }
            $clean[] = $line;
        }
        return implode("\n", array_slice($clean, 0, 1000));
    }

    private static function createTables(): void
    {
        try {
            $db = Db::get();
            $prefix = $db->getPrefix();
            $adapter = $db->getAdapterName();

            if ($adapter === 'Pgsql') {
                $db->query("CREATE TABLE IF NOT EXISTS {$prefix}renew_go_logs (id SERIAL PRIMARY KEY, ip VARCHAR(45) NOT NULL, action VARCHAR(24) NOT NULL, result VARCHAR(16) NOT NULL, target TEXT DEFAULT NULL, referer TEXT DEFAULT NULL, created_at INTEGER NOT NULL)");
                $db->query("CREATE INDEX IF NOT EXISTS idx_renew_go_ip_action_created ON {$prefix}renew_go_logs (ip, action, created_at)");
                $db->query("CREATE INDEX IF NOT EXISTS idx_renew_go_created ON {$prefix}renew_go_logs (created_at)");
                return;
            }

            if ($adapter === 'SQLite') {
                $db->query("CREATE TABLE IF NOT EXISTS {$prefix}renew_go_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT NOT NULL, action TEXT NOT NULL, result TEXT NOT NULL, target TEXT DEFAULT NULL, referer TEXT DEFAULT NULL, created_at INTEGER NOT NULL)");
                $db->query("CREATE INDEX IF NOT EXISTS idx_renew_go_ip_action_created ON {$prefix}renew_go_logs (ip, action, created_at)");
                $db->query("CREATE INDEX IF NOT EXISTS idx_renew_go_created ON {$prefix}renew_go_logs (created_at)");
                return;
            }

            $db->query("CREATE TABLE IF NOT EXISTS {$prefix}renew_go_logs (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45) NOT NULL, action VARCHAR(24) NOT NULL, result VARCHAR(16) NOT NULL, target VARCHAR(512) DEFAULT NULL, referer VARCHAR(512) DEFAULT NULL, created_at INT NOT NULL, KEY idx_ip_action_created (ip, action, created_at), KEY idx_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            self::reportException('createTables', $e);
        }
    }

    private static function ensureConfigStored(): void
    {
        $defaults = self::defaults();
        $raw = [];
        try {
            $raw = (array) Helper::options()->plugin(self::NAME)->toArray();
        } catch (Throwable $e) {
            $raw = [];
            self::reportException('ensureConfigStored.read', $e);
        }
        $merged = self::normalize(array_merge($defaults, $raw));
        try {
            \Widget\Plugins\Edit::configPlugin(self::NAME, $merged);
        } catch (Throwable $e) {
            self::reportException('ensureConfigStored.save', $e);
        }
    }

    private static function clearConfigCache(): void
    {
        Pref::clear(
            self::CACHE_KEY,
            static function (string $scope, Throwable $e): void {
                self::reportException('clearConfigCache.' . $scope, $e);
            }
        );
        self::$runtimeRules = [];
    }

    private static function isEnabled(array $settings): bool
    {
        return ((string) ($settings['enabled'] ?? '0') === '1');
    }

    private static function isRewriteEnabled(array $settings, string $scope): bool
    {
        return in_array($scope, (array) ($settings['rewrite'] ?? []), true);
    }

    private static function extractHref(string $tag): array
    {
        if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $tag, $m)) {
            return [(string) $m[2], (string) $m[1]];
        }
        if (preg_match('/\bhref\s*=\s*([^\s>]+)/i', $tag, $m)) {
            return [trim((string) $m[1], "\"'"), '"'];
        }
        return ['', '"'];
    }

    private static function replaceHref(string $tag, string $url, string $quote): string
    {
        $value = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $tag)) {
            return (string) preg_replace('/\bhref\s*=\s*(["\'])(.*?)\1/i', 'href=' . $quote . $value . $quote, $tag, 1);
        }
        if (preg_match('/\bhref\s*=\s*([^\s>]+)/i', $tag)) {
            return (string) preg_replace('/\bhref\s*=\s*([^\s>]+)/i', 'href=' . $quote . $value . $quote, $tag, 1);
        }
        return rtrim(substr($tag, 0, -1)) . ' href=' . $quote . $value . $quote . '>';
    }

    private static function mergeRel(string $tag, array $rels): string
    {
        $rels = array_values(array_unique(array_filter(array_map('trim', $rels))));
        if (empty($rels)) {
            return $tag;
        }

        $existing = [];
        if (preg_match('/\brel\s*=\s*(["\'])(.*?)\1/i', $tag, $m)) {
            $existing = preg_split('/\s+/', trim((string) $m[2])) ?: [];
            $tag = (string) preg_replace('/\brel\s*=\s*(["\'])(.*?)\1/i', '', $tag, 1);
        }

        $merged = array_values(array_unique(array_filter(array_merge($existing, $rels))));
        $value = htmlspecialchars(implode(' ', $merged), ENT_QUOTES, 'UTF-8');
        return rtrim(substr($tag, 0, -1)) . ' rel="' . $value . '">';
    }

    private static function setTarget(string $tag, string $target): string
    {
        $value = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
        if (preg_match('/\btarget\s*=\s*(["\'])(.*?)\1/i', $tag)) {
            return (string) preg_replace('/\btarget\s*=\s*(["\'])(.*?)\1/i', 'target="' . $value . '"', $tag, 1);
        }
        return rtrim(substr($tag, 0, -1)) . ' target="' . $value . '">';
    }

    private static function injectClientScript(string $html, array $settings): string
    {
        if (stripos($html, 'id="renewgo-client-script"') !== false) {
            return $html;
        }

        $rules = self::parseRules((string) ($settings['whitelist'] ?? ''));
        $siteHost = strtolower((string) parse_url((string) Helper::options()->siteUrl, PHP_URL_HOST));
        $payload = [
            'siteHost' => self::sanitizeJsString($siteHost),
            'exactHost' => self::sanitizeHostArray(array_keys((array) ($rules['exactHost'] ?? []))),
            'wildHost' => self::sanitizeHostArray((array) ($rules['wildHost'] ?? [])),
            'hostPath' => self::sanitizePathArray((array) ($rules['hostPath'] ?? [])),
            'mode' => (string) ($settings['mode'] ?? self::MODE_INTERSTITIAL),
            'openInNewTab' => ((string) ($settings['openInNewTab'] ?? '1') === '1')
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return $html;
        }

        $index = (string) Helper::options()->index;
        $script = '<script id="renewgo-client-script">(function(){var c=' . $json . ';var base='
            . json_encode(rtrim($index, '/'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . ';if(!c||!base){return;}var hostPath=c.hostPath||[];var exactHost={};(c.exactHost||[]).forEach(function(h){exactHost[String(h||"").toLowerCase()]=1;});'
            . 'function wh(url){try{var u=new URL(url,location.href);var h=(u.hostname||"").toLowerCase();if(!h||h===c.siteHost){return true;}if(exactHost[h]){return true;}'
            . 'for(var i=0;i<(c.wildHost||[]).length;i++){var s=String(c.wildHost[i]||"").toLowerCase();if(!s){continue;}if(h===s||h.endsWith("."+s)){return true;}}'
            . 'var hp=(h+(u.pathname||"/")).replace(/^\\/+/, "");for(var j=0;j<hostPath.length;j++){var p=String(hostPath[j]||"").toLowerCase();if(p&&hp.indexOf(p)===0){return true;}}'
            . 'return false;}catch(e){return true;}}'
            . 'function enc(v){try{return btoa(unescape(encodeURIComponent(v))).replace(/\\+/g,"-").replace(/\\//g,"_").replace(/=+$/,"");}catch(e){return"";}}'
            . 'function rw(a){if(!a||a.dataset.renewgo==="1"||a.dataset.renewgoSkip!==undefined){return;}var href=a.getAttribute("href");if(!href||href.charAt(0)==="#"||href.indexOf("mailto:")===0||href.indexOf("javascript:")===0){return;}'
            . 'var u;try{u=new URL(href,location.href);}catch(e){return;}if(!/^https?:$/i.test(u.protocol)){return;}if(wh(u.href)){return;}var code=enc(u.href);if(!code){return;}a.setAttribute("href",base+"/go/"+code);a.dataset.renewgo="1";'
            . 'if(c.openInNewTab){a.setAttribute("target","_blank");}var rel=(a.getAttribute("rel")||"").split(/\\s+/).filter(Boolean);["nofollow","noopener","noreferrer"].forEach(function(x){if(rel.indexOf(x)<0){rel.push(x);}});a.setAttribute("rel",rel.join(" "));}'
            . 'function scan(root){(root||document).querySelectorAll&& (root||document).querySelectorAll("a[href]").forEach(rw);}scan(document);var mo=new MutationObserver(function(ms){ms.forEach(function(m){m.addedNodes&&m.addedNodes.forEach(function(n){if(n&&n.nodeType===1){if(n.matches&&n.matches("a[href]")){rw(n);}scan(n);}});});});'
            . 'mo.observe(document.documentElement,{childList:true,subtree:true});})();</script>';

        if (stripos($html, '</body>') !== false) {
            return (string) preg_replace('/<\/body>/i', $script . '</body>', $html, 1);
        }
        if (stripos($html, '</html>') !== false) {
            return (string) preg_replace('/<\/html>/i', $script . '</html>', $html, 1);
        }
        return $html . $script;
    }

    private static function sanitizeJsString(string $value): string
    {
        return preg_replace('/[^\w\.\-]/', '', $value) ?? '';
    }

    private static function sanitizeHostArray(array $hosts): array
    {
        $result = [];
        foreach ($hosts as $host) {
            $host = strtolower(trim((string) $host));
            if ($host !== '' && preg_match('/^[a-z0-9][a-z0-9\.\-]*[a-z0-9]$/', $host)) {
                $result[] = $host;
            }
        }
        return $result;
    }

    private static function sanitizePathArray(array $paths): array
    {
        $result = [];
        foreach ($paths as $path) {
            $path = strtolower(trim((string) $path, '/'));
            if ($path !== '' && preg_match('/^[a-z0-9][a-z0-9\.\-\/]*$/', $path)) {
                $result[] = $path;
            }
        }
        return $result;
    }

    private static function reportException(string $scope, Throwable $e): void
    {
        error_log('RenewGo.' . $scope . ': ' . $e->getMessage());
    }
}
