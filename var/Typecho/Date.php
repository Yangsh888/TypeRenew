<?php

namespace Typecho;

class Date
{
    public static int $timezoneOffset = 0;
    public static int $serverTimezoneOffset = 0;
    public static int $serverTimeStamp = 0;
    public int $timeStamp = 0;
    public string $year;
    public string $month;
    public string $day;

    /**
     * 初始化参数
     *
     * @param integer|null $time 时间戳
     */
    public function __construct(?int $time = null)
    {
        $this->timeStamp = (null === $time ? self::time() : $time)
            + (self::$timezoneOffset - self::$serverTimezoneOffset);

        $this->year = date('Y', $this->timeStamp);
        $this->month = date('m', $this->timeStamp);
        $this->day = date('d', $this->timeStamp);
    }

    /**
     * 设置当前期望的时区偏移
     *
     * @param integer $offset
     */
    public static function setTimezoneOffset(int $offset)
    {
        self::$timezoneOffset = $offset;
        self::$serverTimezoneOffset = idate('Z');
    }

    /**
     * 获取格式化时间
     *
     * @param string $format 时间格式
     * @return string
     */
    public function format(string $format): string
    {
        return date($format, $this->timeStamp);
    }

    /**
     * 获取国际化偏移时间
     * @return string
     */
    public function word(): string
    {
        return I18n::dateWord($this->timeStamp, self::time() + (self::$timezoneOffset - self::$serverTimezoneOffset));
    }

    /**
     * 获取服务器时间
     * @return int
     */
    public static function time(): int
    {
        return self::$serverTimeStamp ?: (self::$serverTimeStamp = time());
    }
}
