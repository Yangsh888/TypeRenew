<?php

namespace Typecho;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

class Timezone
{
    private const DEFAULT_NAME = 'Asia/Shanghai';
    private static ?DateTimeZone $zone = null;

    public static function boot(?string $name = null, ?int $legacyOffset = null): void
    {
        $resolvedName = self::resolve($name, $legacyOffset);

        self::$zone = new DateTimeZone($resolvedName);
    }

    public static function resolve(?string $name = null, ?int $legacyOffset = null): string
    {
        $candidate = trim((string) $name);

        if (self::isValidName($candidate)) {
            return $candidate;
        }

        if (null !== $legacyOffset) {
            return self::guessRegionFromLegacyOffset($legacyOffset);
        }

        return self::DEFAULT_NAME;
    }

    public static function isValidName(string $name): bool
    {
        $name = trim($name);
        if ($name === '' || self::isOffsetName($name)) {
            return false;
        }

        try {
            new DateTimeZone($name);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getName(): string
    {
        return self::getZone()->getName();
    }

    public static function getZone(): DateTimeZone
    {
        self::ensureBooted();
        return self::$zone;
    }

    public static function serverOffset(): int
    {
        return idate('Z');
    }

    public static function offsetAt(?int $timestamp = null): int
    {
        $moment = self::at($timestamp ?? Date::time());
        return $moment->getOffset();
    }

    public static function offsetFromName(string $name, ?int $timestamp = null): int
    {
        try {
            $zone = new DateTimeZone($name);
        } catch (Exception $e) {
            return 0;
        }

        return $zone->getOffset(new DateTimeImmutable('@' . ($timestamp ?? Date::time())));
    }

    public static function at(int $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone(self::getZone());
    }

    public static function format(int $timestamp, string $format): string
    {
        return self::at($timestamp)->format($format);
    }

    public static function formatAtom(int $timestamp): string
    {
        return self::format($timestamp, DATE_ATOM);
    }

    public static function formatRfc822(int $timestamp): string
    {
        return self::format($timestamp, DATE_RFC2822);
    }

    public static function fromLocalString(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $dateTime = self::createStrictDateTime('!' . $format, $value, self::getZone());
            if ($dateTime instanceof DateTimeImmutable && $dateTime->format($format) === $value) {
                return $dateTime->getTimestamp();
            }
        }

        return null;
    }

    public static function fromLocalParts(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0
    ): ?int {
        if (!self::isValidLocalDate($year, $month, $day) || !self::isValidLocalTime($hour, $minute, $second)) {
            return null;
        }

        $value = sprintf('%d-%d-%d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
        $dateTime = self::createStrictDateTime('!Y-n-j H:i:s', $value, self::getZone());

        if (!$dateTime instanceof DateTimeImmutable || $dateTime->format('Y-n-j H:i:s') !== $value) {
            return null;
        }

        return $dateTime->getTimestamp();
    }

    public static function dayRange(int $year, int $month, int $day): ?array
    {
        $from = self::fromLocalParts($year, $month, $day);
        if (null === $from) {
            return null;
        }

        return [
            $from,
            self::at($from)->modify('+1 day')->getTimestamp(),
        ];
    }

    public static function monthRange(int $year, int $month): ?array
    {
        $from = self::fromLocalParts($year, $month, 1);
        if (null === $from) {
            return null;
        }

        return [
            $from,
            self::at($from)->modify('+1 month')->getTimestamp(),
        ];
    }

    public static function yearRange(int $year): ?array
    {
        $from = self::fromLocalParts($year, 1, 1);
        if (null === $from) {
            return null;
        }

        return [
            $from,
            self::at($from)->modify('+1 year')->getTimestamp(),
        ];
    }

    public static function isValidLocalDate(int $year, int $month = 1, int $day = 1): bool
    {
        return $year > 0 && checkdate($month, $day, $year);
    }

    public static function isValidLocalTime(int $hour, int $minute = 0, int $second = 0): bool
    {
        return $hour >= 0 && $hour <= 23
            && $minute >= 0 && $minute <= 59
            && $second >= 0 && $second <= 59;
    }

    public static function formatDateParts(int $timestamp): array
    {
        $dateTime = self::at($timestamp);
        return [
            'year' => $dateTime->format('Y'),
            'month' => $dateTime->format('m'),
            'day' => $dateTime->format('d'),
        ];
    }

    public static function selectOptions(): array
    {
        $options = [];

        foreach (self::recommendedOptions() as $name => $label) {
            $options[$name] = $label;
        }

        foreach (self::regionIdentifiers() as $identifier) {
            if (isset($options[$identifier])) {
                continue;
            }

            $options[$identifier] = str_replace('_', ' ', $identifier);
        }

        return $options;
    }

    private static function recommendedOptions(): array
    {
        return [
            'Asia/Shanghai' => _t('推荐 · Asia/Shanghai'),
            'Asia/Tokyo' => _t('推荐 · Asia/Tokyo'),
            'UTC' => _t('推荐 · UTC'),
            'Europe/London' => _t('推荐 · Europe/London'),
            'America/New_York' => _t('推荐 · America/New_York'),
        ];
    }

    private static function regionIdentifiers(): array
    {
        return array_values(array_filter(
            DateTimeZone::listIdentifiers(),
            static function (string $identifier): bool {
                return $identifier === 'UTC'
                    || (
                        strpos($identifier, '/') !== false
                        && strpos($identifier, 'Etc/') !== 0
                        && strpos($identifier, 'SystemV/') !== 0
                    );
            }
        ));
    }

    private static function createStrictDateTime(
        string $format,
        string $value,
        ?DateTimeZone $zone = null
    ): ?DateTimeImmutable {
        $dateTime = DateTimeImmutable::createFromFormat($format, $value, $zone ?? self::getZone());
        if (!$dateTime instanceof DateTimeImmutable) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();
        if (
            is_array($errors)
            && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)
        ) {
            return null;
        }

        return $dateTime;
    }

    private static function guessRegionFromLegacyOffset(int $offset): string
    {
        foreach (array_keys(self::recommendedOptions()) as $identifier) {
            if (self::offsetFromName($identifier, Date::time()) === $offset) {
                return $identifier;
            }
        }

        foreach (self::regionIdentifiers() as $identifier) {
            if (self::offsetFromName($identifier, Date::time()) === $offset) {
                return $identifier;
            }
        }

        return $offset === 0 ? 'UTC' : self::DEFAULT_NAME;
    }

    private static function isOffsetName(string $name): bool
    {
        return preg_match('/^[+-](?:0\d|1\d|2[0-3]):[0-5]\d$/', $name) === 1;
    }

    private static function ensureBooted(): void
    {
        if (self::$zone instanceof DateTimeZone) {
            return;
        }

        self::boot();
    }
}
