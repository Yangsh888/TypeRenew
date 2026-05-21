<?php

namespace IXR;

class Date
{
    private string $year;

    private string $month;

    private string $day;

    private string $hour;

    private string $minute;

    private string $second;

    private string $timezone;

    public function __construct($time)
    {
        if ($time instanceof \DateTimeInterface) {
            $this->parseDateTime($time);
        } elseif (is_numeric($time)) {
            $this->parseTimestamp(intval($time));
        } else {
            $this->parseIso($time);
        }
    }

    private function parseTimestamp(int $timestamp)
    {
        $this->year = gmdate('Y', $timestamp);
        $this->month = gmdate('m', $timestamp);
        $this->day = gmdate('d', $timestamp);
        $this->hour = gmdate('H', $timestamp);
        $this->minute = gmdate('i', $timestamp);
        $this->second = gmdate('s', $timestamp);
        $this->timezone = '';
    }

    private function parseDateTime(\DateTimeInterface $dateTime): void
    {
        $this->year = $dateTime->format('Y');
        $this->month = $dateTime->format('m');
        $this->day = $dateTime->format('d');
        $this->hour = $dateTime->format('H');
        $this->minute = $dateTime->format('i');
        $this->second = $dateTime->format('s');
        $this->timezone = '';
    }

    private function parseIso(string $iso)
    {
        $this->year = substr($iso, 0, 4);
        $this->month = substr($iso, 4, 2);
        $this->day = substr($iso, 6, 2);
        $this->hour = substr($iso, 9, 2);
        $this->minute = substr($iso, 12, 2);
        $this->second = substr($iso, 15, 2);
        $this->timezone = substr($iso, 17);
    }

    public function getIso(): string
    {
        return $this->year . $this->month . $this->day . 'T' . $this->hour . ':' . $this->minute . ':' . $this->second . $this->timezone;
    }

    public function getXml(): string
    {
        return '<dateTime.iso8601>' . $this->getIso() . '</dateTime.iso8601>';
    }

    public function getTimestamp()
    {
        if ($this->timezone === '') {
            $format = '!Ymd\TH:i:s';
        } elseif (str_ends_with($this->timezone, 'Z')) {
            $format = '!Ymd\TH:i:s\Z';
        } else {
            $format = str_contains($this->timezone, ':') ? '!Ymd\TH:i:sP' : '!Ymd\TH:i:sO';
        }

        $dateTime = \DateTimeImmutable::createFromFormat(
            $format,
            $this->getIso(),
            new \DateTimeZone(date_default_timezone_get())
        );

        return $dateTime instanceof \DateTimeImmutable ? $dateTime->getTimestamp() : false;
    }
}
