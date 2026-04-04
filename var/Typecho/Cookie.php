<?php

namespace Typecho;

class Cookie
{
    private static string $prefix = '';

    private static string $path = '/';

    private static string $domain = '';

    private static bool $secure = true;

    private static bool $httponly = true;
    private static string $sameSite = 'Lax';

    /**
     * 获取前缀
     * @return string
     */
    public static function getPrefix(): string
    {
        return self::$prefix;
    }

    /**
     * 设置前缀
     *
     * @param string $url
     */
    public static function setPrefix(string $url)
    {
        self::$prefix = md5($url);
        $parsed = parse_url($url);

        self::$domain = $parsed['host'];
        self::$path = empty($parsed['path']) ? '/' : Common::url(null, $parsed['path']);
        self::$secure = isset($parsed['scheme']) && strtolower((string) $parsed['scheme']) === 'https';
    }

    /**
     * 获取目录
     * @return string
     */
    public static function getPath(): string
    {
        return self::$path;
    }

    /**
     * @return string
     */
    public static function getDomain(): string
    {
        return self::$domain;
    }

    /**
     * @return bool
     */
    public static function getSecure(): bool
    {
        return self::$secure ?: false;
    }

    /**
     * 设置额外的选项
     *
     * @param array $options
     */
    public static function setOptions(array $options)
    {
        self::$domain = $options['domain'] ?: self::$domain;
        if (array_key_exists('secure', $options)) {
            self::$secure = (bool) $options['secure'];
        }
        if (array_key_exists('httponly', $options)) {
            self::$httponly = (bool) $options['httponly'];
        }
        $sameSite = ucfirst(strtolower((string) ($options['samesite'] ?? $options['sameSite'] ?? self::$sameSite)));
        self::$sameSite = in_array($sameSite, ['Lax', 'Strict', 'None'], true) ? $sameSite : 'Lax';
        if (self::$sameSite === 'None' && !self::$secure) {
            self::$sameSite = 'Lax';
        }
    }

    /**
     * 获取指定的COOKIE值
     *
     * @param string $key 指定的参数
     * @param string|null $default 默认的参数
     * @return mixed
     */
    public static function get(string $key, ?string $default = null)
    {
        $key = self::$prefix . $key;
        $value = $_COOKIE[$key] ?? $default;
        return is_array($value) ? $default : $value;
    }

    /**
     * 设置指定的COOKIE值
     *
     * @param string $key 指定的参数
     * @param mixed $value 设置的值
     * @param integer $expire 过期时间,默认为0,表示随会话时间结束
     */
    public static function set(string $key, $value, int $expire = 0)
    {
        $key = self::$prefix . $key;
        $_COOKIE[$key] = $value;
        Response::getInstance()->setCookie(
            $key,
            $value,
            $expire,
            self::$path,
            self::$domain,
            self::$secure,
            self::$httponly,
            self::$sameSite
        );
    }

    /**
     * 删除指定的COOKIE值
     *
     * @param string $key 指定的参数
     */
    public static function delete(string $key)
    {
        $key = self::$prefix . $key;
        if (!isset($_COOKIE[$key])) {
            return;
        }

        Response::getInstance()->setCookie($key, '', -1, self::$path, self::$domain, self::$secure, self::$httponly, self::$sameSite);
        unset($_COOKIE[$key]);
    }
}
