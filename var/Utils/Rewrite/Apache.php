<?php

namespace Utils\Rewrite;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Apache
{
    public static function render(string $basePath): string
    {
        $basePath = Manager::normalizeBasePath($basePath);
        $target = $basePath === '/' ? '/index.php' : rtrim($basePath, '/') . '/index.php';

        return implode("\n", [
            '# TypeRenew rewrite rules',
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            'RewriteBase ' . $basePath,
            'RewriteCond %{REQUEST_FILENAME} !-f',
            'RewriteCond %{REQUEST_FILENAME} !-d',
            'RewriteRule . ' . $target . ' [L]',
            '</IfModule>',
        ]);
    }

    public static function managedBlock(string $basePath): string
    {
        return implode("\n", [
            Manager::BLOCK_BEGIN,
            self::render($basePath),
            Manager::BLOCK_END,
        ]);
    }
}
