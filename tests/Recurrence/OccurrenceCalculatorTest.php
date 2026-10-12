<?php

namespace App\Tests\Recurrence;

use App\Entity\Event\RecurringEvent;
use App\Recurrence\OccurrenceCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OccurrenceCalculatorTest extends TestCase
{
    private function series(string $rule, string $start = '2026-01-03 10:00'): RecurringEvent
    {
        $series = new RecurringEvent();
        $series->startDate = new DateTimeImmutable($start);
        $series->endDate = $series->startDate->modify('+5 hours');
        $series->rrule = $rule;

        return $series;
    }

    private static function format(array $dates): array
    {
        return array_map(fn(DateTimeImmutable $date) => $date->format('Y-m-d H:i'), $dates);
    }

    public function testMonthlyNthWeekdayKeepsTheTimeAcrossDst(): void
    {
        $slots = OccurrenceCalculator::between(
            $this->series('FREQ=MONTHLY;BYDAY=1SA'),
            new DateTimeImmutable('2026-03-01'),
            new DateTimeImmutable('2026-05-01'),
        );

        self::assertSame(['2026-03-07 10:00', '2026-04-04 10:00'], self::format($slots));
    }

    public function testUntilIsAnInclusiveLocalDate(): void
    {
        $slots = OccurrenceCalculator::next($this->series('FREQ=WEEKLY;UNTIL=20260117'), new DateTimeImmutable('2026-01-01'), 10);

        self::assertSame(['2026-01-03 10:00', '2026-01-10 10:00', '2026-01-17 10:00'], self::format($slots));
    }

    public function testNextWorksForEndlessRules(): void
    {
        $series = $this->series('FREQ=MONTHLY;BYDAY=1SA');
        $series->exclude(new DateTimeImmutable('2026-02-07'));

        self::assertSame(
            ['2026-01-03 10:00', '2026-03-07 10:00'],
            self::format(OccurrenceCalculator::next($series, new DateTimeImmutable('2026-01-01'), 2)),
        );
    }

    public function testExceptionDatesAreSkipped(): void
    {
        $series = $this->series('FREQ=WEEKLY;COUNT=3');
        $series->exclude(new DateTimeImmutable('2026-01-10'));

        self::assertSame(
            ['2026-01-03 10:00', '2026-01-17 10:00'],
            self::format(OccurrenceCalculator::next($series, new DateTimeImmutable('2026-01-01'), 10)),
        );
    }

    public function testDescribe(): void
    {
        self::assertSame(
            'every 2 months on the first Monday of the month',
            OccurrenceCalculator::describe($this->series('FREQ=MONTHLY;INTERVAL=2;BYDAY=1MO')),
        );
        self::assertSame('weekly, until 17 Jan 2026', OccurrenceCalculator::describe($this->series('FREQ=WEEKLY;UNTIL=20260117')));
    }

    public static function invalidRules(): iterable
    {
        yield 'sub-daily' => ['FREQ=HOURLY'];
        yield 'no frequency' => ['INTERVAL=2'];
        yield 'bad weekday' => ['FREQ=WEEKLY;BYDAY=XX'];
        yield 'bad end date' => ['FREQ=WEEKLY;UNTIL=soon'];
    }

    #[DataProvider('invalidRules')]
    public function testValidateRejects(string $rule): void
    {
        self::assertNotNull(OccurrenceCalculator::validate($this->series($rule)));
    }

    public function testParseAndComposeRoundTrip(): void
    {
        $parts = OccurrenceCalculator::parse('RRULE:until=20270101;FREQ=MONTHLY;BYDAY=-1FR');

        self::assertSame(['UNTIL' => '20270101', 'FREQ' => 'MONTHLY', 'BYDAY' => '-1FR'], $parts);
        self::assertSame('FREQ=MONTHLY;BYDAY=-1FR;UNTIL=20270101', OccurrenceCalculator::compose($parts));
    }
}
