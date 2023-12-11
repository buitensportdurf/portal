<?php

namespace App\Service;

use DateTimeInterface;

class TimeIntervalService
{
    public static function addIntervalNTimes(DateTimeInterface $date, \DateInterval $interval, int $n): DateTimeInterface
    {
        for ($i = 0; $i < $n; $i++) {
            $date = $date->add($interval);
        }
        return $date;
    }
}