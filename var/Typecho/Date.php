<?php

namespace Typecho;

use Utils\Zone;

class Date
{
    public static ?string $timezoneId = null;
    public static int $timezoneOffset = 0;
    public static int $serverTimezoneOffset = 0;
    public static int $serverTimeStamp = 0;
    public int $timeStamp = 0;
    public string $year;
    public string $month;
    public string $day;
    private \DateTimeImmutable $dateTime;

    /**
     * 初始化参数
     *
     * @param integer|null $time 时间戳
     */
    public function __construct(?int $time = null)
    {
        $this->timeStamp = null === $time ? self::time() : $time;
        $this->dateTime = Zone::dateTime($this->timeStamp, self::$timezoneId, self::$timezoneOffset);

        $this->year = $this->dateTime->format('Y');
        $this->month = $this->dateTime->format('m');
        $this->day = $this->dateTime->format('d');
    }

    /**
     * 设置当前期望的时区偏移
     *
     * @param integer $offset
     */
    public static function setTimezoneOffset(int $offset)
    {
        self::$timezoneId = null;
        self::$timezoneOffset = $offset;
        self::$serverTimezoneOffset = Zone::serverOffsetAt(self::time());
    }

    public static function configure(?string $timezoneId, int $offset): void
    {
        self::$timezoneId = Zone::normalizeId($timezoneId);
        self::$timezoneOffset = $offset;
        self::$serverTimezoneOffset = Zone::serverOffsetAt(self::time());
    }

    /**
     * 获取格式化时间
     *
     * @param string $format 时间格式
     * @return string
     */
    public function format(string $format): string
    {
        return $this->dateTime->format($format);
    }

    /**
     * 获取国际化偏移时间
     * @return string
     */
    public function word(): string
    {
        return Zone::word($this->timeStamp, self::time(), self::$timezoneId, self::$timezoneOffset);
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
