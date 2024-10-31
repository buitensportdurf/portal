<?php

namespace App\Service;

use DateInterval;
use DateTimeImmutable;

class TimeIntervalService
{
    /**
     * @param DateInterval[] $intervals
     */
    public static function addIntervalsNTimes(DateTimeImmutable $date, array $intervals, int $n = 1): DateTimeImmutable
    {
        for ($i = 0; $i < $n; $i++) {
            foreach ($intervals as $interval) {
                $date = $date->add($interval);
            }
        }
        return $date;
    }
}