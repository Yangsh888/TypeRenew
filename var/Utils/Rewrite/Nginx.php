<?php

namespace Utils\Rewrite;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Nginx
{
    public static function render(string $basePath): string
    {
        $basePath = Manager::normalizeBasePath($basePath);
        $location = $basePath;
        $target = $basePath === '/' ? '/index.php$is_args$args' : rtrim($basePath, '/') . '/index.php$is_args$args';
        $rules = ['# TypeRenew rewrite rules'];

        if ($basePath !== '/') {
            $rules[] = 'location = ' . rtrim($location, '/') . ' {';
            $rules[] = '    try_files $uri $uri/ ' . $target . ';';
            $rules[] = '}';
        }

        $rules[] = 'location ^~ ' . $location . ' {';
        $rules[] = '    try_files $uri $uri/ ' . $target . ';';
        $rules[] = '}';

        return implode("\n", $rules);
    }
}
