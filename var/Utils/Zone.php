<?php

namespace Utils;

use DateTimeImmutable;
use DateTimeZone;

class Zone
{
    private static function matchLocal(DateTimeImmutable $dateTime, string $expected): bool
    {
        return $dateTime->format('Y-m-d H:i:s') === $expected;
    }

    public static function normalizeId(?string $timezoneId): ?string
    {
        static $identifiers = null;

        $timezoneId = is_string($timezoneId) ? trim($timezoneId) : '';
        if ($timezoneId === '') {
            return null;
        }

        if ($identifiers === null) {
            $identifiers = array_flip(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC));
        }

        return isset($identifiers[$timezoneId]) ? $timezoneId : null;
    }

    public static function normalizeStoredId(?string $timezoneId): ?string
    {
        $timezoneId = self::normalizeId($timezoneId) ?? (is_string($timezoneId) ? trim($timezoneId) : '');
        if ($timezoneId === '') {
            return null;
        }

        if (preg_match('/^[+-]\d{2}:\d{2}$/', $timezoneId)) {
            return $timezoneId;
        }

        return self::normalizeId($timezoneId);
    }

    public static function legacyId(int $offset): string
    {
        if ($offset === 0) {
            return 'Etc/UTC';
        }

        $minutes = abs($offset) % 3600;
        if ($minutes !== 0) {
            return self::offsetString($offset);
        }

        return 'Etc/GMT' . ($offset > 0 ? '-' : '+') . (string) (abs($offset) / 3600);
    }

    public static function zone(?string $timezoneId, int $fallbackOffset = 0): DateTimeZone
    {
        $timezoneId = self::normalizeId($timezoneId);
        if ($timezoneId !== null) {
            return new DateTimeZone($timezoneId);
        }

        return new DateTimeZone(self::offsetString($fallbackOffset));
    }

    public static function offsetAt(?string $timezoneId, int $fallbackOffset = 0, ?int $timestamp = null): int
    {
        $timestamp = $timestamp ?? time();
        return self::zone($timezoneId, $fallbackOffset)
            ->getOffset(new DateTimeImmutable('@' . $timestamp));
    }

    public static function serverOffsetAt(?int $timestamp = null): int
    {
        $timestamp = $timestamp ?? time();
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->getOffset();
    }

    public static function dateTime(?int $timestamp = null, ?string $timezoneId = null, int $fallbackOffset = 0): DateTimeImmutable
    {
        $timestamp = $timestamp ?? time();
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone(self::zone($timezoneId, $fallbackOffset));
    }

    public static function utcDateTime(?int $timestamp = null): DateTimeImmutable
    {
        $timestamp = $timestamp ?? time();
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
    }

    public static function fromParts(
        int $year,
        int $month,
        int $day,
        int $hour,
        int $minute,
        int $second,
        ?string $timezoneId = null,
        int $fallbackOffset = 0
    ): ?int {
        $local = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
        $dateTime = new DateTimeImmutable($local, self::zone($timezoneId, $fallbackOffset));

        return self::matchLocal($dateTime, $local) ? $dateTime->getTimestamp() : null;
    }

    public static function fromString(
        string $date,
        string $time = '00:00:00',
        ?string $timezoneId = null,
        int $fallbackOffset = 0
    ): ?int {
        $date = trim($date);
        $time = trim($time);

        if ($date === '') {
            return null;
        }

        $local = $date . ' ' . ($time === '' ? '00:00:00' : $time);
        $dateTime = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $local,
            self::zone($timezoneId, $fallbackOffset)
        );

        return $dateTime instanceof DateTimeImmutable && self::matchLocal($dateTime, $local)
            ? $dateTime->getTimestamp()
            : null;
    }

    public static function range(
        int $year,
        ?int $month = null,
        ?int $day = null,
        ?string $timezoneId = null,
        int $fallbackOffset = 0
    ): array {
        $zone = self::zone($timezoneId, $fallbackOffset);

        if ($month !== null && $day !== null) {
            $start = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $zone);
            $end = $start->modify('+1 day');
        } elseif ($month !== null) {
            $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $zone);
            $end = $start->modify('+1 month');
        } else {
            $start = new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year), $zone);
            $end = $start->modify('+1 year');
        }

        return [$start->getTimestamp(), $end->getTimestamp()];
    }

    public static function offsetString(int $offset): string
    {
        $sign = $offset >= 0 ? '+' : '-';
        $offset = abs($offset);
        $hours = intdiv($offset, 3600);
        $minutes = intdiv($offset % 3600, 60);

        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }

    public static function word(int $from, int $now, ?string $timezoneId = null, int $fallbackOffset = 0): string
    {
        $between = $now - $from;
        $fromDate = self::dateTime($from, $timezoneId, $fallbackOffset);
        $nowDate = self::dateTime($now, $timezoneId, $fallbackOffset);

        if ($between >= 0 && $between < 86400 && $fromDate->format('Y-m-d') === $nowDate->format('Y-m-d')) {
            if ($between < 3600) {
                if ($between < 60) {
                    if (0 == $between) {
                        return _t('刚刚');
                    }

                    return str_replace('%d', (string) $between, _n('一秒前', '%d秒前', $between));
                }

                $min = (int) floor($between / 60);
                return str_replace('%d', (string) $min, _n('一分钟前', '%d分钟前', $min));
            }

            $hour = (int) floor($between / 3600);
            return str_replace('%d', (string) $hour, _n('一小时前', '%d小时前', $hour));
        }

        if ($between > 0 && $between < 172800 && $fromDate->modify('+1 day')->format('Y-m-d') === $nowDate->format('Y-m-d')) {
            return _t('昨天 %s', $fromDate->format('H:i'));
        }

        if ($between > 0 && $between < 604800) {
            $day = (int) floor($between / 86400);
            return str_replace('%d', (string) $day, _n('一天前', '%d天前', $day));
        }

        if ($fromDate->format('Y') === $nowDate->format('Y')) {
            return $fromDate->format(_t('n月j日'));
        }

        return $fromDate->format(_t('Y年m月d日'));
    }
}
