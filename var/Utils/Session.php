<?php

namespace Utils;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Session
{
    public static function active(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public static function start(): bool
    {
        if (self::active()) {
            return true;
        }

        if (session_status() === PHP_SESSION_DISABLED || headers_sent()) {
            return false;
        }

        $secure = \Typecho\Request::getInstance()->isSecure();

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        return @session_start();
    }

    public static function rotate(): bool
    {
        if (!self::start() || headers_sent()) {
            return false;
        }

        return @session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        if (!self::active()) {
            return;
        }

        $name = session_name();
        $params = session_get_cookie_params();
        $_SESSION = [];
        @session_destroy();

        if (!$name || headers_sent() || !ini_get('session.use_cookies')) {
            return;
        }

        setcookie($name, '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }
}
