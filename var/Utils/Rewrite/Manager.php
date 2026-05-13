<?php

namespace Utils\Rewrite;

use Typecho\Common;
use Typecho\Db;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Manager
{
    public const RULES_HEADER = '# TypeRenew rewrite rules';

    public static function status(Options $options): array
    {
        $enabled = self::enabled($options);
        $state = self::normalizeStoredState([
            'rewriteStatus' => (string) ($options->rewriteStatus ?? ''),
            'rewriteVerifiedAt' => (string) ($options->rewriteVerifiedAt ?? '0'),
            'rewriteMessage' => (string) ($options->rewriteMessage ?? ''),
        ], $enabled);
        $basePath = self::basePathFromUrl((string) $options->siteUrl);

        return [
            'status' => $state['rewriteStatus'],
            'verifiedAt' => (int) $state['rewriteVerifiedAt'],
            'message' => $state['rewriteMessage'],
            'basePath' => $basePath,
            'nginxRules' => Nginx::render($basePath),
            'apacheRules' => Apache::render($basePath),
        ];
    }

    public static function normalizeStoredState(array $state, bool $enabled): array
    {
        $message = trim((string) ($state['rewriteMessage'] ?? ''));
        $status = self::normalizeStatus((string) ($state['rewriteStatus'] ?? ''));
        $verifiedAt = trim((string) ($state['rewriteVerifiedAt'] ?? '0'));
        $verifiedAt = preg_match('/^\d+$/', $verifiedAt) === 1 ? (string) ((int) $verifiedAt) : '0';

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
            $message = self::defaultMessage($enabled, $status);
        }

        return [
            'rewriteStatus' => $status,
            'rewriteMessage' => $message,
            'rewriteVerifiedAt' => $verifiedAt,
        ];
    }

    public static function cleanupLegacyOptions(Db $db): void
    {
        $db->query(
            $db->delete('table.options')
                ->where('user = ? AND name IN ?', 0, ['rewriteServer', 'rewriteMode'])
        );
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

    public static function publicProbePath(Options $options): string
    {
        return Common::url('__tr/rewrite-probe/', $options->rootUrl);
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

    private static function normalizeComparableUrl(string $url): string
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

    private static function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'verified' => 'verified',
            'disabled' => 'disabled',
            default => 'pending',
        };
    }

    private static function defaultMessage(bool $enabled, string $status): string
    {
        if (!$enabled) {
            return self::disabledMessage();
        }

        if ($status === 'verified') {
            return self::verifiedMessage();
        }

        return _t('地址重写已启用，请确认已完成规则配置。');
    }

    public static function verifiedMessage(): string
    {
        return _t('地址重写配置校验通过。');
    }

    public static function disabledMessage(): string
    {
        return _t('当前未启用地址重写。');
    }

    public static function expiredProbeMessage(): string
    {
        return _t('验证请求已失效，请刷新页面后重试。');
    }

}
