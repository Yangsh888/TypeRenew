<?php

namespace Typecho\Mail;

use Widget\Options;

class Template
{
    private const ALLOWED = ['owner', 'guest', 'notice', 'reset'];

    public static function render(string $name, array $vars, Options $options): string
    {
        $content = self::load($name, $options);
        return self::replace($content, $vars);
    }

    public static function load(string $name, Options $options): string
    {
        $file = self::resolve($name, $options);
        if (!self::validatePath($file) || !is_readable($file)) {
            return '';
        }

        $content = file_get_contents($file);
        return is_string($content) ? $content : '';
    }

    private static function validatePath(string $path): bool
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            return false;
        }

        $baseDirs = [
            rtrim(str_replace(['\\', '//'], ['/', '/'], __TYPECHO_ROOT_DIR__ . '/usr/mail'), '/'),
            rtrim(str_replace(['\\', '//'], ['/', '/'], __TYPECHO_ROOT_DIR__ . '/var/Typecho/Mail/tpl'), '/'),
        ];

        foreach ($baseDirs as $base) {
            $baseReal = realpath($base);
            if ($baseReal !== false && str_starts_with($realPath, $baseReal)) {
                return true;
            }
        }

        return false;
    }

    public static function resolve(string $name, Options $options): string
    {
        $name = self::normalizeName($name);
        $fileName = $name . '.html';

        $theme = (string) $options->theme;
        $themeFile = $options->themeFile($theme, 'mail/' . $fileName);
        if (is_string($themeFile) && file_exists($themeFile)) {
            return $themeFile;
        }

        $usrFile = __TYPECHO_ROOT_DIR__ . '/usr/mail/' . $fileName;
        if (file_exists($usrFile)) {
            return $usrFile;
        }

        return __TYPECHO_ROOT_DIR__ . '/var/Typecho/Mail/tpl/' . $fileName;
    }

    public static function writeOverride(string $name, string $content, Options $options): bool|string
    {
        $name = self::normalizeName($name);
        if (!in_array($name, self::ALLOWED, true)) {
            return 'Invalid template name';
        }

        $theme = (string) $options->theme;
        $dir = $options->themeFile($theme, 'mail');
        $dir = rtrim(str_replace(['\\', '//'], ['/', '/'], $dir), '/');
        $themeRoot = rtrim(str_replace(['\\', '//'], ['/', '/'], $options->themeFile($theme)), '/');

        if (strpos($dir, $themeRoot) !== 0) {
            return 'Invalid theme path';
        }

        if (!is_dir($dir)) {
            if (!is_dir($themeRoot) || !is_writable($themeRoot)) {
                return 'Cannot create directory';
            }

            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                return 'Cannot create directory';
            }
        }

        $file = $dir . '/' . $name . '.html';
        if (file_exists($file) && !is_writable($file)) {
            return 'File not writable';
        }

        if (!is_writable($dir)) {
            return 'File not writable';
        }

        $ok = file_put_contents($file, $content) !== false;
        return $ok ? true : 'Write failed';
    }

    public static function deleteOverride(string $name, Options $options): bool|string
    {
        $name = self::normalizeName($name);
        if (!in_array($name, self::ALLOWED, true)) {
            return 'Invalid template name';
        }

        $theme = (string) $options->theme;
        $file = $options->themeFile($theme, 'mail/' . $name . '.html');
        if (!file_exists($file)) {
            return true;
        }

        if (!is_writable($file)) {
            return 'File not writable';
        }

        return unlink($file) ? true : 'Delete failed';
    }

    public static function normalizeName(string $name): string
    {
        $name = preg_replace('/[^a-z0-9_]/i', '', $name);
        if (!is_string($name) || $name === '') {
            $name = 'owner';
        }

        return $name;
    }

    private static function replace(string $content, array $vars): string
    {
        return (string) preg_replace_callback('/\{(raw:)?([a-zA-Z0-9_]+)\}/', static function (array $matches) use ($vars): string {
            $isRaw = !empty($matches[1]);
            $key = (string) ($matches[2] ?? '');
            $value = $vars[$key] ?? '';
            if (is_array($value) || is_object($value)) {
                $value = '';
            }
            $value = (string) $value;

            if ($isRaw) {
                return $value;
            }

            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $content) ?? $content;
    }
}
