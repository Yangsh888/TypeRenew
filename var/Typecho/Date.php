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

    public function __construct(?int $time = null)
    {
        $this->timeStamp = null === $time ? self::time() : $time;
        $this->dateTime = Zone::dateTime($this->timeStamp, self::$timezoneId, self::$timezoneOffset);

        $this->year = $this->dateTime->format('Y');
        $this->month = $this->dateTime->format('m');
        $this->day = $this->dateTime->format('d');
    }

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

    public function format(string $format): string
    {
        return $this->dateTime->format($format);
    }

    public function word(): string
    {
        return Zone::word($this->timeStamp, self::time(), self::$timezoneId, self::$timezoneOffset);
    }

    public static function time(): int
    {
        return self::$serverTimeStamp ?: (self::$serverTimeStamp = time());
    }
}
