<?php

namespace Typecho;

use Typecho\I18n\GetTextMulti;
use Utils\Zone;

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
        return Zone::word($from, $now, Date::$timezoneId, Date::$timezoneOffset);
    }

    public static function addLang(string $lang)
    {
        self::init();

        if (!self::$loaded) {
            self::$loaded = new GetTextMulti($lang);
            return;
        }

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
