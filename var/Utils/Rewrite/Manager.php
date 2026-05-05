<?php

namespace Utils\Rewrite;

use Typecho\Common;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Manager
{
    public const BLOCK_BEGIN = '# BEGIN TypeRenew';
    public const BLOCK_END = '# END TypeRenew';

    public static function status(Options $options): array
    {
        $enabled = self::enabled($options);
        $state = self::normalizeStoredState([
            'rewriteServer' => (string) ($options->rewriteServer ?? ''),
            'rewriteMode' => (string) ($options->rewriteMode ?? ''),
            'rewriteStatus' => (string) ($options->rewriteStatus ?? ''),
            'rewriteVerifiedAt' => (string) ($options->rewriteVerifiedAt ?? '0'),
            'rewriteMessage' => (string) ($options->rewriteMessage ?? ''),
        ], $enabled);
        $server = $state['rewriteServer'];
        $mode = $state['rewriteMode'];
        $managed = self::canManageApache($server, $mode);
        $basePath = self::basePath($options);

        return [
            'server' => $server,
            'mode' => $mode,
            'status' => $state['rewriteStatus'],
            'verifiedAt' => (int) $state['rewriteVerifiedAt'],
            'message' => $state['rewriteMessage'],
            'basePath' => $basePath,
            'apacheRules' => Apache::render($basePath),
            'nginxRules' => Nginx::render($basePath),
            'managed' => $managed,
        ];
    }

    public static function persistState(array $settings, string $rewrite): array
    {
        $rewriteEnabled = $rewrite === '1';

        return ['rewrite' => $rewriteEnabled ? '1' : '0'] + self::normalizeStoredState($settings, $rewriteEnabled);
    }

    public static function metadata(bool $enabled, string $server, string $mode): array
    {
        return self::normalizeStoredState([
            'rewriteServer' => $server,
            'rewriteMode' => $mode,
        ], $enabled);
    }

    public static function normalizeStoredState(array $state, bool $enabled): array
    {
        [$server, $mode] = self::normalizeServerMode(
            (string) ($state['rewriteServer'] ?? ''),
            (string) ($state['rewriteMode'] ?? '')
        );
        $status = self::normalizeStatus((string) ($state['rewriteStatus'] ?? ''));
        $verifiedAt = trim((string) ($state['rewriteVerifiedAt'] ?? '0'));
        $verifiedAt = preg_match('/^\d+$/', $verifiedAt) === 1 ? (string) ((int) $verifiedAt) : '0';
        $message = trim((string) ($state['rewriteMessage'] ?? ''));

        if (!$enabled) {
            $status = 'disabled';
            $verifiedAt = '0';
        } elseif ($status === 'disabled') {
            $status = 'pending';
            $verifiedAt = '0';
        } elseif ($status !== 'verified') {
            $verifiedAt = '0';
        }

        if ($message === '') {
            $message = self::defaultMessage($enabled, $mode, $status);
        }

        return [
            'rewriteServer' => $server,
            'rewriteMode' => $mode,
            'rewriteStatus' => $status,
            'rewriteMessage' => $message,
            'rewriteVerifiedAt' => $verifiedAt,
        ];
    }

    public static function inferLegacyServer(bool $rewriteEnabled): string
    {
        if (!$rewriteEnabled) {
            return 'nginx';
        }

        $file = self::apachePath();
        if (!is_file($file) || !is_readable($file)) {
            return 'other';
        }

        $content = (string) file_get_contents($file);
        return preg_match('/RewriteEngine\s+On|RewriteRule|RewriteCond|mod_rewrite/i', $content) === 1
            ? 'apache'
            : 'other';
    }

    public static function writeManagedApache(string $basePath): bool
    {
        $file = self::apachePath();
        $existing = is_file($file) ? (string) file_get_contents($file) : '';
        $block = Apache::managedBlock($basePath);

        if ($existing === '') {
            return file_put_contents($file, $block . "\n") !== false;
        }

        $pattern = '/' . preg_quote(self::BLOCK_BEGIN, '/') . '[\s\S]*?' . preg_quote(self::BLOCK_END, '/') . '\n?/u';
        if (preg_match($pattern, $existing) === 1) {
            $updated = preg_replace($pattern, $block . "\n", $existing);
            if (is_string($updated)) {
                return file_put_contents($file, $updated) !== false;
            }
            return false;
        }

        $separator = str_ends_with($existing, "\n") ? '' : "\n";
        return file_put_contents($file, $existing . $separator . $block . "\n") !== false;
    }

    public static function removeManagedApache(): bool
    {
        $file = self::apachePath();
        if (!is_file($file)) {
            return true;
        }

        if (!is_readable($file) || !is_writable($file)) {
            return false;
        }

        $existing = (string) file_get_contents($file);
        $pattern = '/' . preg_quote(self::BLOCK_BEGIN, '/') . '[\s\S]*?' . preg_quote(self::BLOCK_END, '/') . '\n?/u';
        $updated = preg_replace($pattern, '', $existing);
        if (!is_string($updated) || $updated === $existing) {
            return true;
        }

        return file_put_contents($file, trim($updated) === '' ? '' : rtrim($updated) . "\n") !== false;
    }

    public static function snapshotApacheConfig(): array
    {
        $file = self::apachePath();
        if (!is_file($file)) {
            return ['exists' => false, 'content' => ''];
        }

        if (!is_readable($file)) {
            return ['exists' => true, 'content' => null];
        }

        return [
            'exists' => true,
            'content' => (string) file_get_contents($file),
        ];
    }

    public static function restoreApacheConfig(array $snapshot): bool
    {
        $file = self::apachePath();
        $exists = (bool) ($snapshot['exists'] ?? false);
        $content = $snapshot['content'] ?? '';

        if (!$exists) {
            return !is_file($file) || @unlink($file);
        }

        if (!is_string($content)) {
            return false;
        }

        return file_put_contents($file, $content) !== false;
    }

    public static function verify(Options $options, string $token): bool
    {
        return $token !== '' && hash_equals(self::token($options), $token);
    }

    public static function enabled(Options $options): bool
    {
        return defined('__TYPECHO_REWRITE__')
            ? (bool) __TYPECHO_REWRITE__
            : (bool) $options->rewrite;
    }

    public static function token(Options $options): string
    {
        return sha1(implode('|', [
            (string) ($options->secret ?? ''),
            (string) ($options->siteUrl ?? ''),
            self::enabled($options) ? '1' : '0',
        ]));
    }

    public static function probePath(Options $options): string
    {
        return Common::url('action/ajax?do=rewriteProbe&token=' . rawurlencode(self::token($options)), $options->rootUrl);
    }

    public static function basePath(Options $options): string
    {
        return self::basePathFromUrl((string) $options->siteUrl);
    }

    public static function basePathFromUrl(string $siteUrl): string
    {
        $parsed = Common::parseUrl($siteUrl);
        return self::normalizeBasePath((string) ($parsed['path'] ?? ''));
    }

    public static function normalizeBasePath(string $basePath): string
    {
        $basePath = trim(str_replace('\\', '/', $basePath));
        if ($basePath === '' || $basePath === '/') {
            return '/';
        }

        return '/' . trim($basePath, '/') . '/';
    }

    public static function sameSiteLocation(string $left, string $right): bool
    {
        return self::normalizeComparableUrl($left) === self::normalizeComparableUrl($right);
    }

    public static function normalizeComparableUrl(string $url): string
    {
        $parts = Common::parseUrl(trim($url));
        if ($parts === []) {
            return rtrim(trim($url), '/');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = (int) ($parts['port'] ?? 0);
        $path = self::normalizeComparablePath((string) ($parts['path'] ?? '/'));

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = 0;
        }

        return implode('|', [
            $scheme,
            $host,
            $port > 0 ? (string) $port : '',
            $path,
        ]);
    }

    private static function normalizeComparablePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }

    public static function canManageApache(string $server, string $mode): bool
    {
        return $mode === 'managed' && $server === 'apache';
    }

    public static function canWriteApacheConfig(): bool
    {
        $file = self::apachePath();
        return is_file($file)
            ? is_readable($file) && is_writable($file)
            : is_writable(__TYPECHO_ROOT_DIR__);
    }

    public static function normalizeServer(string $server): string
    {
        return match (strtolower(trim($server))) {
            'apache' => 'apache',
            'other' => 'other',
            default => 'nginx',
        };
    }

    public static function normalizeMode(string $mode): string
    {
        return strtolower(trim($mode)) === 'managed' ? 'managed' : 'manual';
    }

    public static function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'verified' => 'verified',
            'disabled' => 'disabled',
            default => 'pending',
        };
    }

    private static function defaultMessage(bool $enabled, string $mode, string $status): string
    {
        if (!$enabled) {
            return _t('当前未启用地址重写。');
        }

        if ($status === 'verified') {
            return _t('已验证当前地址重写配置可以正常工作。');
        }

        return $mode === 'managed'
            ? _t('地址重写已启用，当前由系统维护 Apache 规则区块。')
            : _t('地址重写已启用，请确认服务器已完成规则配置。');
    }

    private static function normalizeServerMode(string $server, string $mode): array
    {
        $server = self::normalizeServer($server);
        $mode = self::normalizeMode($mode);

        if (!self::canManageApache($server, $mode)) {
            $mode = 'manual';
        }

        return [$server, $mode];
    }

    private static function apachePath(): string
    {
        return __TYPECHO_ROOT_DIR__ . '/.htaccess';
    }
}
