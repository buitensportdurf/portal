<?php

namespace App\Tests\Entity\Event;

use App\Entity\Event\Event;
use App\Entity\Event\RecurringEvent;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RecurringEventTest extends TestCase
{
    private function createRecurringEvent(string $rule = '1 week'): RecurringEvent
    {
        $event = new RecurringEvent();
        $event->setName('Weekly Event');
        $event->setLocation('Club');
        $event->setStartDate(new DateTimeImmutable('2025-01-06 18:00:00'));
        $event->setDuration(new DateInterval('PT2H'));
        $event->setRecurrenceRule($rule);

        return $event;
    }

    // --- Recurrence Rule Parsing ---

    public function testGetRecurrenceIntervalsSingleRule(): void
    {
        $event = $this->createRecurringEvent('1 week');
        $intervals = $event->getRecurrenceIntervals();

        self::assertCount(1, $intervals);
        self::assertInstanceOf(DateInterval::class, $intervals[0]);
    }

    public function testGetRecurrenceIntervalsMultipleRules(): void
    {
        $event = $this->createRecurringEvent('1 day;2 weeks');
        $intervals = $event->getRecurrenceIntervals();

        self::assertCount(2, $intervals);
    }

    // --- Recurring Date Calculation ---

    public function testGetRecurringDateFirstOccurrence(): void
    {
        $event = $this->createRecurringEvent('1 week');
        $next = $event->getRecurringDate(1);

        self::assertSame('2025-01-13', $next->format('Y-m-d'));
        self::assertSame('18:00:00', $next->format('H:i:s'));
    }

    public function testGetRecurringDateThirdOccurrence(): void
    {
        $event = $this->createRecurringEvent('1 week');
        $third = $event->getRecurringDate(3);

        self::assertSame('2025-01-27', $third->format('Y-m-d'));
    }

    public function testGetRecurringDateZeroIndexReturnsSameDate(): void
    {
        $event = $this->createRecurringEvent('1 week');
        $same = $event->getRecurringDate(0);

        self::assertSame('2025-01-06', $same->format('Y-m-d'));
    }

    public function testGetRecurringDateWithCustomDate(): void
    {
        $event = $this->createRecurringEvent('2 days');
        $custom = new DateTimeImmutable('2025-03-01 10:00:00');
        $next = $event->getRecurringDate(1, $custom);

        self::assertSame('2025-03-03', $next->format('Y-m-d'));
    }

    public function testGetRecurringDateCompoundRule(): void
    {
        // "1 week;1 day" means each recurrence adds 1 week + 1 day = 8 days
        $event = $this->createRecurringEvent('1 week;1 day');
        $next = $event->getRecurringDate(1);

        self::assertSame('2025-01-14', $next->format('Y-m-d'));
    }

    // --- Create Next Event ---

    public function testCreateNextEventFromRecurringEvent(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $event = $recurring->createNextEvent();

        self::assertInstanceOf(Event::class, $event);
        self::assertSame('Weekly Event', $event->getName());
        self::assertSame('Club', $event->getLocation());
        self::assertSame('2025-01-13', $event->getStartDate()->format('Y-m-d'));
        self::assertSame('18:00', $event->getStartDate()->format('H:i'));
        self::assertSame($recurring, $event->getRecurringEvent());
    }

    public function testCreateNextEventPreservesTime(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $event = $recurring->createNextEvent();

        self::assertSame('18:00:00', $event->getStartDate()->format('H:i:s'));
    }

    public function testCreateNextEventChainsBuildOnPrevious(): void
    {
        $recurring = $this->createRecurringEvent('1 week');

        $first = $recurring->createNextEvent();
        self::assertSame('2025-01-13', $first->getStartDate()->format('Y-m-d'));

        $second = $recurring->createNextEvent();
        self::assertSame('2025-01-20', $second->getStartDate()->format('Y-m-d'));

        $third = $recurring->createNextEvent();
        self::assertSame('2025-01-27', $third->getStartDate()->format('Y-m-d'));
    }

    public function testCreateNextEventCopiesProperties(): void
    {
        $recurring = $this->createRecurringEvent('2 weeks');
        $recurring->setDescription('A recurring event');
        $recurring->setMemberPrice(10.00);
        $recurring->setGuestsAllowed(true);
        $recurring->setSubscriberLimit(15);

        $event = $recurring->createNextEvent();

        self::assertSame('A recurring event', $event->getDescription());
        self::assertSame(10.00, $event->getMemberPrice());
        self::assertTrue($event->isGuestsAllowed());
        self::assertSame(15, $event->getSubscriberLimit());
    }

    public function testCreateNextEventIsAddedToEventsCollection(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        self::assertCount(0, $recurring->getEvents());

        $recurring->createNextEvent();
        self::assertCount(1, $recurring->getEvents());

        $recurring->createNextEvent();
        self::assertCount(2, $recurring->getEvents());
    }

    // --- Future and Past Events ---

    public function testGetFutureEvents(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $recurring->setStartDate(new DateTimeImmutable('-3 weeks'));

        // Create 4 events: 3 past, 1 future
        $recurring->createNextEvent(); // -2 weeks
        $recurring->createNextEvent(); // -1 week
        $recurring->createNextEvent(); // now-ish
        $recurring->createNextEvent(); // +1 week

        $future = $recurring->getFutureEvents();
        self::assertGreaterThanOrEqual(1, $future->count());
    }

    // --- Events Collection ---

    public function testAddAndRemoveEvent(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $event = new Event();
        $event->setName('Child');
        $event->setLocation('Here');
        $event->setStartDate(new DateTimeImmutable('+1 week'));
        $event->setDuration(new DateInterval('PT1H'));

        $recurring->addEvent($event);
        self::assertCount(1, $recurring->getEvents());
        self::assertSame($recurring, $event->getRecurringEvent());

        $recurring->removeEvent($event);
        self::assertCount(0, $recurring->getEvents());
        self::assertNull($event->getRecurringEvent());
    }

    public function testAddEventIdempotent(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $event = $recurring->createNextEvent();

        $recurring->addEvent($event); // already added by createNextEvent
        self::assertCount(1, $recurring->getEvents());
    }

    // --- Monthly recurrence ---

    public function testMonthlyRecurrence(): void
    {
        $event = $this->createRecurringEvent('1 month');
        $event->setStartDate(new DateTimeImmutable('2025-01-15 19:00:00'));

        $first = $event->createNextEvent();
        self::assertSame('2025-02-15', $first->getStartDate()->format('Y-m-d'));

        $second = $event->createNextEvent();
        self::assertSame('2025-03-15', $second->getStartDate()->format('Y-m-d'));
    }
}
