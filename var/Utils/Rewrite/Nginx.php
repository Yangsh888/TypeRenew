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
        $rules = [Manager::RULES_HEADER];

        if ($basePath === '/') {
            $rules[] = 'location / {';
            $rules[] = '    if (!-e $request_filename) {';
            $rules[] = '        rewrite ^(.*)$ /index.php$1 last;';
            $rules[] = '    }';
            $rules[] = '}';
            return implode("\n", $rules);
        }

        $prefix = rtrim($basePath, '/');
        $pattern = preg_quote($prefix, '/');
        $rules[] = 'location = ' . $prefix . ' {';
        $rules[] = '    return 301 ' . $prefix . '/;';
        $rules[] = '}';
        $rules[] = 'location ' . $basePath . ' {';
        $rules[] = '    if (!-e $request_filename) {';
        $rules[] = '        rewrite ^' . $pattern . '(.*)$ ' . $prefix . '/index.php$1 last;';
        $rules[] = '    }';
        $rules[] = '}';

        return implode("\n", $rules);
    }
}
