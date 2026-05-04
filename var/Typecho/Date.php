<?php

namespace Typecho;

use DateTimeImmutable;

class Date
{
    public static int $serverTimeStamp = 0;
    public int $timeStamp = 0;
    public string $year;
    public string $month;
    public string $day;
    private DateTimeImmutable $dateTime;

    public function __construct(?int $time = null)
    {
        $this->timeStamp = null === $time ? self::time() : $time;
        $this->dateTime = Timezone::at($this->timeStamp);

        $this->year = $this->dateTime->format('Y');
        $this->month = $this->dateTime->format('m');
        $this->day = $this->dateTime->format('d');
    }

    public static function setTimezone(?string $name = null, ?int $legacyOffset = null): void
    {
        Timezone::boot($name, $legacyOffset);
    }

    public function format(string $format): string
    {
        return $this->dateTime->format($format);
    }

    public function word(): string
    {
        return I18n::dateWord($this->timeStamp, self::time());
    }

    public static function time(): int
    {
        return self::$serverTimeStamp ?: (self::$serverTimeStamp = time());
    }
}
