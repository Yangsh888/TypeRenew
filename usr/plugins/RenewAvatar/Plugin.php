<?php
/**
 * 【TypeRenew 专用】头像策略拓展
 *
 * @package RenewAvatar
 * @author TypeRenew
 * @version 1.0.0
 * @link https://github.com/Yangsh888/TypeRenew
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use Typecho\Cache;
use Typecho\Common;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Utils\NoPersonal;
use Utils\Pref;

class RenewAvatar_Plugin implements PluginInterface
{
    use NoPersonal;

    private const NAME = 'RenewAvatar';
    private const CACHE_KEY = 'renewavatar:settings:v1';
    private const FAIL_MARK = '__FAIL__';

    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Comments')->gravatar = ['RenewAvatar_Plugin', 'render'];
        Typecho_Plugin::factory('admin/header.php')->header = ['RenewAvatar_Plugin', 'filterAdminHeader'];
        Typecho_Plugin::factory('index.php')->begin = ['RenewAvatar_Plugin', 'bootstrapPrefix'];
        self::ensureConfigStored();
        self::clearCache();
        return _t('RenewAvatar 已启用');
    }

    public static function deactivate()
    {
        self::clearCache();
    }

    public static function config(Form $form)
    {
        $settings = self::getSettings();

        $enabled = new Form\Element\Radio(
            'enabled',
            ['1' => _t('启用插件'), '0' => _t('停用插件')],
            (string) $settings['enabled'],
            _t('插件状态')
        );
        $form->addInput($enabled);

        $applyGlobal = new Form\Element\Radio(
            'applyGlobalPrefix',
            ['1' => _t('全局替换 Gravatar 源（含后台头像）'), '0' => _t('仅替换评论头像')],
            (string) $settings['applyGlobalPrefix'],
            _t('全局策略')
        );
        $form->addInput($applyGlobal);

        $mirror = new Form\Element\Radio(
            'mirror',
            self::mirrorOptions(),
            (string) $settings['mirror'],
            _t('头像镜像源')
        );
        $form->addInput($mirror->multiMode());

        $customMirror = new Form\Element\Text(
            'customMirror',
            null,
            (string) $settings['customMirror'],
            _t('自定义镜像'),
            _t('仅支持 https:// 开头，格式建议 https://xxx/avatar')
        );
        $customMirror->input->setAttribute('class', 'w-100 mono');
        $form->addInput($customMirror);

        $priority = new Form\Element\Radio(
            'priority',
            ['qq' => _t('优先 QQ 邮箱头像'), 'gr' => _t('优先 Gravatar 镜像')],
            (string) $settings['priority'],
            _t('优先策略')
        );
        $form->addInput($priority);

        $qq = new Form\Element\Radio(
            'enableQQ',
            ['1' => _t('启用'), '0' => _t('关闭')],
            (string) $settings['enableQQ'],
            _t('QQ 邮箱头像支持')
        );
        $form->addInput($qq);

        $default = new Form\Element\Radio(
            'defaultAvatar',
            [
                'mm' => _t('神秘人'),
                'blank' => _t('空白'),
                'identicon' => _t('抽象图形'),
                'wavatar' => _t('Wavatar'),
                'monsterid' => _t('小怪物')
            ],
            (string) $settings['defaultAvatar'],
            _t('默认头像'),
            _t('当用户没有 Gravatar 头像时显示的默认图片样式')
        );
        $form->addInput($default);

        $ttl = new Form\Element\Number(
            'cacheTtl',
            null,
            (int) $settings['cacheTtl'],
            _t('缓存秒数')
        );
        $ttl->input->setAttribute('class', 'w-20');
        $form->addInput($ttl->addRule('isInteger', _t('请填入一个数字')));

        $timeout = new Form\Element\Number(
            'requestTimeout',
            null,
            (int) $settings['requestTimeout'],
            _t('外部请求超时（秒）')
        );
        $timeout->input->setAttribute('class', 'w-20');
        $form->addInput($timeout->addRule('isInteger', _t('请填入一个数字')));
    }

    public static function configHandle(array &$settings, bool $isInit)
    {
        $settings = self::normalize($settings);
        \Widget\Plugins\Edit::configPlugin(self::NAME, $settings);
        self::clearCache();
        self::bootstrapPrefix(true);
    }

    public static function render($size, $rating, $default, $comments): void
    {
        $settings = self::getSettings();
        if (empty($settings['enabled'])) {
            $url = Common::gravatarUrl((string) ($comments->mail ?? ''), (int) $size, (string) $rating, $default, $comments->request->isSecure());
        } else {
            $url = self::avatarUrl((string) ($comments->mail ?? ''), (int) $size, (string) $rating, $default, (bool) $comments->request->isSecure(), $settings);
        }
        $author = htmlspecialchars((string) ($comments->author ?? ''), ENT_QUOTES, 'UTF-8');
        echo '<img class="avatar" loading="lazy" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . $author . '" width="' . (int) $size . '" height="' . (int) $size . '" />';
    }

    public static function filterAdminHeader(string $header): string
    {
        self::bootstrapPrefix();
        return $header;
    }

    public static function bootstrapPrefix(bool $force = false): void
    {
        static $booted = false;
        if ($booted && !$force) {
            return;
        }
        $booted = true;

        if (defined('__TYPECHO_GRAVATAR_PREFIX__')) {
            return;
        }

        try {
            $settings = self::getSettings();
        } catch (Throwable $e) {
            return;
        }

        if (empty($settings['enabled']) || empty($settings['applyGlobalPrefix'])) {
            return;
        }
        $prefix = self::mirrorPrefix($settings);
        if ($prefix !== '') {
            define('__TYPECHO_GRAVATAR_PREFIX__', $prefix);
        }
    }

    private static function avatarUrl(string $mail, int $size, string $rating, ?string $default, bool $isSecure, array $settings): string
    {
        $size = max(16, min(512, $size));
        $rating = $rating !== '' ? $rating : 'G';
        $defaultAvatar = (string) ($settings['defaultAvatar'] ?? 'mm');
        $defaultValue = $default !== null ? (string) $default : $defaultAvatar;
        $normalized = self::normalizeMail($mail);
        if ($normalized === '') {
            return self::gravatarWithHash('', $size, $rating, $defaultValue, $isSecure, $settings);
        }

        if (!empty($settings['enableQQ']) && ($settings['priority'] ?? 'qq') === 'qq') {
            $qq = self::qqAvatarUrl($normalized, $settings);
            if ($qq !== '') {
                return $qq;
            }
        }

        $hash = md5($normalized);
        return self::gravatarWithHash($hash, $size, $rating, $defaultValue, $isSecure, $settings);
    }

    private static function gravatarWithHash(string $hash, int $size, string $rating, string $default, bool $isSecure, array $settings): string
    {
        $prefix = self::mirrorPrefix($settings);
        $query = http_build_query([
            's' => $size,
            'r' => $rating,
            'd' => $default
        ], '', '&', PHP_QUERY_RFC3986);
        return $prefix . $hash . '?' . $query;
    }

    private static function qqAvatarUrl(string $mail, array $settings): string
    {
        if (!preg_match('/^(\d{5,11})@qq\.com$/i', $mail, $m)) {
            return '';
        }
        $uin = $m[1];
        $ttl = (int) ($settings['cacheTtl'] ?? 3600);
        $key = 'renewavatar:qq:' . md5($mail);
        $failKey = $key . ':fail';
        
        $cached = self::cacheGet($key);
        if ($cached !== null) {
            return $cached === self::FAIL_MARK ? '' : $cached;
        }

        $failCached = self::cacheGet($failKey);
        $failCount = $failCached !== null ? (int) $failCached : 0;

        $url = self::fetchQqUrl($uin, (int) ($settings['requestTimeout'] ?? 3));
        if ($url !== '') {
            self::cacheSet($key, $url, $ttl);
            self::cacheDelete($failKey);
            return $url;
        }

        $nextFailCount = $failCount + 1;
        $failTtl = self::getProgressiveFailTtl($nextFailCount);
        self::cacheSet($key, self::FAIL_MARK, $failTtl);
        self::cacheSet($failKey, (string) $nextFailCount, $failTtl);
        return '';
    }

    private static function getProgressiveFailTtl(int $failCount): int
    {
        return match ($failCount) {
            1 => 60,
            2 => 180,
            3 => 600,
            default => 1800
        };
    }

    private static function cacheDelete(string $key): void
    {
        try {
            $cache = Cache::getInstance();
            if ($cache->enabled()) {
                $cache->delete($key);
            }
        } catch (Throwable $e) {
            self::reportException('cacheDelete', $e);
        }
    }

    private static function fetchQqUrl(string $uin, int $timeout): string
    {
        $timeout = max(1, min(10, $timeout));
        $url = 'https://ptlogin2.qq.com/getface?appid=1006102&uin=' . rawurlencode($uin) . '&imgtype=3';
        $raw = self::request($url, $timeout);
        if ($raw === '') {
            return '';
        }
        if (!preg_match('/pt\.setHeader\((.+)\)/is', $raw, $match)) {
            return '';
        }
        $data = json_decode($match[1], true);
        if (!is_array($data)) {
            return '';
        }
        $value = (string) ($data[$uin] ?? '');
        if ($value === '') {
            return '';
        }
        if (strpos($value, 'http://') === 0) {
            $value = 'https://' . substr($value, 7);
        }
        return strpos($value, 'https://') === 0 ? $value : '';
    }

    private static function request(string $url, int $timeout): string
    {
        $parts = @parse_url($url);
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        if ($host === '' || strcasecmp($host, 'ptlogin2.qq.com') !== 0) {
            return '';
        }
        if (!\Typecho\Common::checkSafeHost($host)) {
            return '';
        }

        if (function_exists('curl_init')) {
            $ch = curl_init();
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(3, $timeout));
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                if (defined('CURLOPT_PROTOCOLS')) {
                    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
                }
                if (defined('CURLOPT_REDIR_PROTOCOLS')) {
                    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
                }
                curl_setopt($ch, CURLOPT_USERAGENT, 'TypeRenew');
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                $body = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if (is_string($body) && $body !== '' && $code >= 200 && $code < 400) {
                    return $body;
                }
            }
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
                'header' => "User-Agent: TypeRenew\r\n"
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : '';
    }

    private static function mirrorPrefix(array $settings): string
    {
        $mirror = (string) ($settings['mirror'] ?? 'loli');
        if ($mirror === 'custom') {
            $custom = self::sanitizeCustomMirror((string) ($settings['customMirror'] ?? ''));
            if ($custom !== '') {
                return $custom;
            }
        }

        $map = self::mirrorMap();
        return (string) ($map[$mirror] ?? $map['loli']);
    }

    private static function normalizeMail(string $mail): string
    {
        return strtolower(trim($mail));
    }

    private static function mirrorOptions(): array
    {
        return [
            'loli' => 'Gravatar loli 镜像',
            'qiniu' => '七牛镜像',
            'cravatar' => 'Cravatar 镜像（推荐）',
            'webpse' => 'Webp.se 镜像',
            'weavatar' => 'WeAvatar 镜像',
            'sepcc' => 'Sep.cc 镜像',
            'custom' => '自定义镜像'
        ];
    }

    private static function mirrorMap(): array
    {
        return [
            'loli' => 'https://gravatar.loli.net/avatar/',
            'qiniu' => 'https://dn-qiniu-avatar.qbox.me/avatar/',
            'cravatar' => 'https://cn.cravatar.com/avatar/',
            'webpse' => 'https://gravatar.webp.se/avatar/',
            'weavatar' => 'https://weavatar.com/avatar/',
            'sepcc' => 'https://cdn.sep.cc/avatar/'
        ];
    }

    private static function defaults(): array
    {
        return [
            'enabled' => '1',
            'applyGlobalPrefix' => '1',
            'mirror' => 'loli',
            'customMirror' => '',
            'priority' => 'qq',
            'enableQQ' => '1',
            'defaultAvatar' => 'mm',
            'cacheTtl' => '3600',
            'requestTimeout' => '3'
        ];
    }

    private static function normalize(array $settings): array
    {
        $custom = self::sanitizeCustomMirror((string) ($settings['customMirror'] ?? ''));
        return [
            'enabled' => ((string) ($settings['enabled'] ?? '1') === '1') ? '1' : '0',
            'applyGlobalPrefix' => ((string) ($settings['applyGlobalPrefix'] ?? '1') === '1') ? '1' : '0',
            'mirror' => array_key_exists((string) ($settings['mirror'] ?? 'loli'), self::mirrorOptions()) ? (string) $settings['mirror'] : 'loli',
            'customMirror' => $custom,
            'priority' => ((string) ($settings['priority'] ?? 'qq') === 'gr') ? 'gr' : 'qq',
            'enableQQ' => ((string) ($settings['enableQQ'] ?? '1') === '1') ? '1' : '0',
            'defaultAvatar' => in_array((string) ($settings['defaultAvatar'] ?? 'mm'), ['mm', 'blank', 'identicon', 'wavatar', 'monsterid'], true) ? (string) ($settings['defaultAvatar'] ?? 'mm') : 'mm',
            'cacheTtl' => (string) max(60, min(86400, (int) ($settings['cacheTtl'] ?? 3600))),
            'requestTimeout' => (string) max(1, min(10, (int) ($settings['requestTimeout'] ?? 3)))
        ];
    }

    private static function sanitizeCustomMirror(string $url): string
    {
        $value = trim($url);
        if ($value === '' || stripos($value, 'https://') !== 0) {
            return '';
        }
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $host = strtolower((string) $parts['host']);
        if (!self::isAllowedMirrorHost($host)) {
            return '';
        }
        return rtrim($value, '/') . '/';
    }

    private static function isAllowedMirrorHost(string $host): bool
    {
        $allowed = [
            'gravatar.loli.net',
            'dn-qiniu-avatar.qbox.me',
            'cn.cravatar.com',
            'gravatar.webp.se',
            'weavatar.com',
            'cdn.sep.cc',
            'secure.gravatar.com',
            'www.gravatar.com'
        ];
        if (in_array($host, $allowed, true)) {
            return true;
        }
        foreach ($allowed as $allowedHost) {
            $suffix = '.' . $allowedHost;
            if (str_ends_with($host, $suffix)) {
                $prefix = substr($host, 0, -strlen($suffix));
                if ($prefix !== '' && strpos($prefix, '.') === false) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function getSettings(): array
    {
        static $runtime = null;
        return Pref::load(
            $runtime,
            self::CACHE_KEY,
            self::defaults(),
            static fn() => (array) Widget_Options::alloc()->plugin(self::NAME)->toArray(),
            static fn(array $settings): array => self::normalize($settings),
            static fn() => self::ensureConfigStored(),
            static function (string $scope, Throwable $e): void {
                self::reportException('getSettings.' . $scope, $e);
            }
        );
    }

    private static function ensureConfigStored(): void
    {
        try {
            $existing = (array) Widget_Options::alloc()->plugin(self::NAME)->toArray();
            $merged = self::normalize(array_merge(self::defaults(), $existing));
        } catch (Throwable $e) {
            $merged = self::defaults();
            self::reportException('ensureConfigStored.load', $e);
        }

        try {
            \Widget\Plugins\Edit::configPlugin(self::NAME, $merged);
        } catch (Throwable $e) {
            self::reportException('ensureConfigStored.save', $e);
        }
    }

    private static function clearCache(): void
    {
        Pref::clear(
            self::CACHE_KEY,
            static function (string $scope, Throwable $e): void {
                self::reportException('clearCache.' . $scope, $e);
            }
        );
    }

    private static function cacheGet(string $key): ?string
    {
        try {
            $cache = Cache::getInstance();
            if ($cache->enabled()) {
                $hit = false;
                $value = $cache->get($key, $hit);
                if ($hit && is_string($value)) {
                    return $value;
                }
            }
        } catch (Throwable $e) {
            self::reportException('cacheGet', $e);
        }
        return null;
    }

    private static function cacheSet(string $key, string $value, int $ttl): void
    {
        try {
            $cache = Cache::getInstance();
            if ($cache->enabled()) {
                $cache->set($key, $value, max(60, min(86400, $ttl)));
            }
        } catch (Throwable $e) {
            self::reportException('cacheSet', $e);
        }
    }

    private static function reportException(string $scope, Throwable $e): void
    {
        error_log(self::NAME . '.' . $scope . ': ' . $e->getMessage());
    }
}
