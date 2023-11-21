<?php

namespace App\Service;

use DateTimeInterface;

class TimeIntervalService
{
    public static function getIntervalSeconds(\DateInterval $interval): int
    {
        $seconds = 0;
        $seconds += $interval->s;
        $seconds += $interval->i * 60;
        $seconds += $interval->h * 60 * 60;
        $seconds += $interval->d * 60 * 60 * 24;
        $seconds += $interval->m * 60 * 60 * 24 * 30;
        $seconds += $interval->y * 60 * 60 * 24 * 365;

        return $seconds;
    }

    public static function addIntervalNTimes(DateTimeInterface $date, \DateInterval $interval, int $n): DateTimeInterface
    {
        for ($i = 0; $i < $n; $i++) {
            $date = $date->add($interval);
        }
        return $date;
    }
}