<?php

namespace App\Tests\Form\Event;

use App\Form\Event\OffsetType;
use App\Form\Event\RecurrenceRuleType;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

class RecurrenceRuleTypeTest extends TestCase
{
    public static function rules(): iterable
    {
        yield 'weekly' => ['FREQ=WEEKLY'];
        yield 'every other week on two days' => ['FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,TH'];
        yield 'first saturday' => ['FREQ=MONTHLY;BYDAY=1SA'];
        yield 'last friday every 2 months' => ['FREQ=MONTHLY;INTERVAL=2;BYDAY=-1FR'];
        yield 'monthly on the date' => ['FREQ=MONTHLY'];
        yield 'yearly until' => ['FREQ=YEARLY;UNTIL=20300101'];
        yield 'daily count' => ['FREQ=DAILY;COUNT=5'];
    }

    #[DataProvider('rules')]
    public function testRoundTrip(string $rule): void
    {
        self::assertSame($rule, RecurrenceRuleType::toRule(RecurrenceRuleType::toChoices($rule)));
    }

    public function testWeekdaysAreSortedAndIgnoredForOtherFrequencies(): void
    {
        $choices = RecurrenceRuleType::toChoices('FREQ=WEEKLY');
        $choices['weekdays'] = ['SU', 'MO'];
        self::assertSame('FREQ=WEEKLY;BYDAY=MO,SU', RecurrenceRuleType::toRule($choices));

        $choices['frequency'] = 'DAILY';
        self::assertSame('FREQ=DAILY', RecurrenceRuleType::toRule($choices));
    }

    public function testUntilNeedsADate(): void
    {
        $choices = RecurrenceRuleType::toChoices('FREQ=WEEKLY');
        $choices['end'] = 'until';

        $this->expectException(TransformationFailedException::class);
        RecurrenceRuleType::toRule($choices);
    }

    public function testUntilChoice(): void
    {
        $choices = RecurrenceRuleType::toChoices('FREQ=WEEKLY');
        $choices['end'] = 'until';
        $choices['until'] = new DateTimeImmutable('2027-06-30');

        self::assertSame('FREQ=WEEKLY;UNTIL=20270630', RecurrenceRuleType::toRule($choices));
    }

    public function testOffsetUsesTheLargestWholeUnit(): void
    {
        self::assertSame(['amount' => null, 'unit' => 'days'], OffsetType::toAmount(null));
        self::assertSame(['amount' => 2, 'unit' => 'weeks'], OffsetType::toAmount(20160));
        self::assertSame(['amount' => 3, 'unit' => 'days'], OffsetType::toAmount(4320));
        self::assertSame(['amount' => 5, 'unit' => 'hours'], OffsetType::toAmount(300));
        self::assertSame(2880, OffsetType::toMinutes(['amount' => 2, 'unit' => 'days']));
        self::assertNull(OffsetType::toMinutes(['amount' => null, 'unit' => 'days']));
    }
}
