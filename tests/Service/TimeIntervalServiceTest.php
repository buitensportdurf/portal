<?php

namespace App\Tests\Service;

use App\Service\TimeIntervalService;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TimeIntervalServiceTest extends TestCase
{
    public function testAddSingleIntervalOnce(): void
    {
        $date = new DateTimeImmutable('2025-01-01 12:00:00');
        $intervals = [DateInterval::createFromDateString('1 week')];

        $result = TimeIntervalService::addIntervalsNTimes($date, $intervals);

        self::assertSame('2025-01-08', $result->format('Y-m-d'));
        self::assertSame('12:00:00', $result->format('H:i:s'));
    }

    public function testAddSingleIntervalMultipleTimes(): void
    {
        $date = new DateTimeImmutable('2025-01-01');
        $intervals = [DateInterval::createFromDateString('1 day')];

        $result = TimeIntervalService::addIntervalsNTimes($date, $intervals, 5);

        self::assertSame('2025-01-06', $result->format('Y-m-d'));
    }

    public function testAddMultipleIntervalsOnce(): void
    {
        $date = new DateTimeImmutable('2025-01-01');
        $intervals = [
            DateInterval::createFromDateString('1 week'),
            DateInterval::createFromDateString('2 days'),
        ];

        $result = TimeIntervalService::addIntervalsNTimes($date, $intervals);

        self::assertSame('2025-01-10', $result->format('Y-m-d'));
    }

    public function testAddMultipleIntervalsMultipleTimes(): void
    {
        $date = new DateTimeImmutable('2025-01-01');
        $intervals = [
            DateInterval::createFromDateString('1 week'),
            DateInterval::createFromDateString('1 day'),
        ];

        // Each iteration adds 8 days, 3 iterations = 24 days
        $result = TimeIntervalService::addIntervalsNTimes($date, $intervals, 3);

        self::assertSame('2025-01-25', $result->format('Y-m-d'));
    }

    public function testAddZeroTimesReturnsSameDate(): void
    {
        $date = new DateTimeImmutable('2025-06-15');
        $intervals = [DateInterval::createFromDateString('1 year')];

        $result = TimeIntervalService::addIntervalsNTimes($date, $intervals, 0);

        self::assertSame('2025-06-15', $result->format('Y-m-d'));
    }

    public function testAddEmptyIntervalsReturnsSameDate(): void
    {
        $date = new DateTimeImmutable('2025-06-15');

        $result = TimeIntervalService::addIntervalsNTimes($date, [], 5);

        self::assertSame('2025-06-15', $result->format('Y-m-d'));
    }

    public function testPreservesTimeComponent(): void
    {
        $date = new DateTimeImmutable('2025-01-01 14:30:45');
        $intervals = [DateInterval::createFromDateString('3 hours')];

        $result = TimeIntervalService::addIntervalsNTimes($date, $intervals);

        self::assertSame('17:30:45', $result->format('H:i:s'));
    }

    public function testMonthBoundary(): void
    {
        $date = new DateTimeImmutable('2025-01-31');
        $intervals = [DateInterval::createFromDateString('1 month')];

        $result = TimeIntervalService::addIntervalsNTimes($date, $intervals);

        // PHP DateInterval behavior: Jan 31 + 1 month = Mar 3 (28 days in Feb)
        self::assertSame('2025-03', $result->format('Y-m'));
    }
}
