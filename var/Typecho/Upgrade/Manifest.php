<?php

namespace Typecho\Upgrade;

use RuntimeException;

class Manifest
{
    public static function parse(string $raw): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('升级包清单无效');
        }

        $product = (string) ($data['product'] ?? '');
        $from = (string) ($data['from'] ?? '');
        $to = (string) ($data['to'] ?? '');

        if ($product !== 'TypeRenew') {
            throw new RuntimeException('升级包产品标识不匹配');
        }

        if (!self::isVersion($from) || !self::isVersion($to)) {
            throw new RuntimeException('升级包版本格式无效');
        }

        if (version_compare($to, $from, '<=')) {
            throw new RuntimeException('升级包目标版本必须高于来源版本');
        }

        $files = [];
        if (isset($data['files'])) {
            if (!is_array($data['files'])) {
                throw new RuntimeException('升级包文件列表格式无效');
            }

            foreach ($data['files'] as $file) {
                $normalized = self::normalize((string) $file);
                if ($normalized === '') {
                    continue;
                }
                $files[$normalized] = $normalized;
            }
        }

        $allowInstall = (bool) ($data['allowInstall'] ?? false);

        return [
            'product' => $product,
            'from' => $from,
            'to' => $to,
            'build' => (string) ($data['build'] ?? ''),
            'hash' => (string) ($data['hash'] ?? ''),
            'allowInstall' => $allowInstall,
            'files' => array_values($files)
        ];
    }

    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        return ltrim($path, '/');
    }

    public static function validatePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }

        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\//', $path)) {
            return false;
        }

        if (preg_match('#(^|/)\.\.($|/)#', $path)) {
            return false;
        }

        return true;
    }

    private static function isVersion(string $value): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', $value);
    }
}
