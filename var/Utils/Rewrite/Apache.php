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

        return implode("\n", [
            Manager::RULES_HEADER,
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            'RewriteBase ' . $basePath,
            'RewriteCond %{REQUEST_FILENAME} !-f',
            'RewriteCond %{REQUEST_FILENAME} !-d',
            'RewriteRule ^(.*)$ index.php/$1 [L]',
            '</IfModule>',
        ]);
    }
}
