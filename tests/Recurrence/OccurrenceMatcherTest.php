<?php

namespace App\Tests\Recurrence;

use App\Recurrence\OccurrenceMatcher;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class OccurrenceMatcherTest extends TestCase
{
    private static function dates(string ...$dates): array
    {
        return array_map(fn(string $date) => new DateTimeImmutable($date), $dates);
    }

    public function testPairsEachItemWithTheNearestSlot(): void
    {
        $result = OccurrenceMatcher::match(
            ['a' => new DateTimeImmutable('2026-01-03 10:00'), 'b' => new DateTimeImmutable('2026-02-07 10:00')],
            self::dates('2026-01-04 10:00', '2026-02-01 10:00', '2026-03-01 10:00'),
        );

        self::assertSame(['a' => 0, 'b' => 1], $result['pairs']);
        self::assertSame([], $result['unmatchedItems']);
        self::assertSame([2], $result['unmatchedSlots']);
    }

    public function testItemsTooFarFromAnySlotStayUnmatched(): void
    {
        $result = OccurrenceMatcher::match(
            ['a' => new DateTimeImmutable('2026-01-01 10:00'), 'b' => new DateTimeImmutable('2026-01-04 10:00')],
            self::dates('2026-01-01 10:00', '2026-01-08 10:00'),
        );

        self::assertSame(['a' => 0], $result['pairs']);
        self::assertSame(['b'], $result['unmatchedItems']);
        self::assertSame([1], $result['unmatchedSlots']);
    }

    public function testHalfwayItemAcrossDstStillMatchesTheEarlierSlot(): void
    {
        // 7 Mar → 4 Apr 2026 crosses the DST switch, so the gap is 28 days minus an hour
        $result = OccurrenceMatcher::match(
            ['apr' => new DateTimeImmutable('2026-04-18 10:00'), 'may' => new DateTimeImmutable('2026-05-16 10:00')],
            self::dates('2026-03-07 10:00', '2026-04-04 10:00', '2026-05-02 10:00', '2026-06-06 10:00'),
        );

        self::assertSame(['apr' => 1, 'may' => 2], $result['pairs']);
    }
}
