<?php

namespace Typecho;

use Typecho\I18n\GetTextMulti;

class I18n
{
    private static ?GetTextMulti $loaded = null;
    private static ?string $lang = null;

    public static function translate(string $string): string
    {
        self::init();
        return self::$loaded ? self::$loaded->translate($string) : $string;
    }

    private static function init()
    {
        if (!isset(self::$loaded) && self::$lang && file_exists(self::$lang)) {
            self::$loaded = new GetTextMulti(self::$lang);
        }
    }

    public static function ngettext(string $single, string $plural, int $number): string
    {
        self::init();
        return self::$loaded ? self::$loaded->ngettext($single, $plural, $number) : ($number > 1 ? $plural : $single);
    }

    public static function dateWord(int $from, int $now): string
    {
        $between = $now - $from;

        if ($between >= 0 && $between < 86400 && date('d', $from) == date('d', $now)) {
            if ($between < 3600) {
                if ($between < 60) {
                    if (0 == $between) {
                        return _t('刚刚');
                    } else {
                        return str_replace('%d', $between, _n('一秒前', '%d秒前', $between));
                    }
                }

                $min = floor($between / 60);
                return str_replace('%d', $min, _n('一分钟前', '%d分钟前', $min));
            }

            $hour = floor($between / 3600);
            return str_replace('%d', $hour, _n('一小时前', '%d小时前', $hour));
        }

        if (
            $between > 0
            && $between < 172800
            && (date('z', $from) + 1 == date('z', $now)
                || date('z', $from) + 1 == date('L') + 365 + date('z', $now))
        ) {
            return _t('昨天 %s', date('H:i', $from));
        }

        if ($between > 0 && $between < 604800) {
            $day = floor($between / 86400);
            return str_replace('%d', $day, _n('一天前', '%d天前', $day));
        }

        if (date('Y', $from) == date('Y', $now)) {
            return date(_t('n月j日'), $from);
        }

        return date(_t('Y年m月d日'), $from);
    }

    public static function addLang(string $lang)
    {
        self::$loaded->addFile($lang);
    }

    public static function getLang(): ?string
    {
        return self::$lang;
    }

    public static function setLang(string $lang)
    {
        self::$lang = $lang;
    }
}
