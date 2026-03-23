<?php

namespace App\Tests\Entity\Event;

use App\Entity\Event\Event;
use App\Entity\Event\RecurringEvent;
use App\Entity\Event\Tag;
use App\Entity\Image;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RecurringEventTest extends TestCase
{
    private function createRecurringEvent(string $rule = '1 week'): RecurringEvent
    {
        $event = new RecurringEvent();
        $event->name = 'Weekly Event';
        $event->location = 'Club';
        $event->startDate = new DateTimeImmutable('2025-01-06 18:00:00');
        $event->endDate = $event->startDate->modify('+2 hours');
        $event->recurrenceRule = $rule;

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

    public function testCompoundRuleAccumulatesOverMultipleOccurrences(): void
    {
        // "1 week;1 day" = 8 days per occurrence
        $event = $this->createRecurringEvent('1 week;1 day');
        // Start: 2025-01-06

        $second = $event->getRecurringDate(2); // +16 days
        self::assertSame('2025-01-22', $second->format('Y-m-d'));

        $third = $event->getRecurringDate(3); // +24 days
        self::assertSame('2025-01-30', $third->format('Y-m-d'));
    }

    public function testCompoundRuleCreateNextEventChaining(): void
    {
        // "2 weeks;3 days" = 17 days per occurrence
        $recurring = $this->createRecurringEvent('2 weeks;3 days');
        // Start: 2025-01-06

        $first = $recurring->createNextEvent();
        self::assertSame('2025-01-23', $first->startDate->format('Y-m-d')); // +17 days

        $second = $recurring->createNextEvent();
        self::assertSame('2025-02-09', $second->startDate->format('Y-m-d')); // +17 days

        $third = $recurring->createNextEvent();
        self::assertSame('2025-02-26', $third->startDate->format('Y-m-d')); // +17 days
    }

    public function testCompoundRulePreservesTime(): void
    {
        $recurring = $this->createRecurringEvent('1 week;2 days');

        $event = $recurring->createNextEvent();

        self::assertSame('18:00:00', $event->startDate->format('H:i:s'));
    }

    public function testCompoundRuleWithMonthAndDays(): void
    {
        // "1 month;1 week" = 1 month + 7 days per occurrence
        $recurring = $this->createRecurringEvent('1 month;1 week');
        $recurring->startDate = new DateTimeImmutable('2025-01-01 20:00:00');

        $first = $recurring->createNextEvent();
        self::assertSame('2025-02-08', $first->startDate->format('Y-m-d'));

        $second = $recurring->createNextEvent();
        self::assertSame('2025-03-15', $second->startDate->format('Y-m-d'));
    }

    public function testTripleCompoundRule(): void
    {
        // "1 week;2 days;3 hours" = 9 days + 3 hours per occurrence
        $recurring = $this->createRecurringEvent('1 week;2 days;3 hours');
        // Start: 2025-01-06 18:00

        $first = $recurring->createNextEvent();
        self::assertSame('2025-01-15', $first->startDate->format('Y-m-d'));
        self::assertSame('21:00:00', $first->startDate->format('H:i:s'));

        $second = $recurring->createNextEvent();
        self::assertSame('2025-01-25', $second->startDate->format('Y-m-d'));
        self::assertSame('00:00:00', $second->startDate->format('H:i:s'));
    }

    // --- Create Next Event ---

    public function testCreateNextEventFromRecurringEvent(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $event = $recurring->createNextEvent();

        self::assertInstanceOf(Event::class, $event);
        self::assertSame('Weekly Event', $event->name);
        self::assertSame('Club', $event->location);
        self::assertSame('2025-01-13', $event->startDate->format('Y-m-d'));
        self::assertSame('18:00', $event->startDate->format('H:i'));
        self::assertSame($recurring, $event->recurringEvent);
    }

    public function testCreateNextEventPreservesTime(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $event = $recurring->createNextEvent();

        self::assertSame('18:00:00', $event->startDate->format('H:i:s'));
    }

    public function testCreateNextEventChainsBuildOnPrevious(): void
    {
        $recurring = $this->createRecurringEvent('1 week');

        $first = $recurring->createNextEvent();
        self::assertSame('2025-01-13', $first->startDate->format('Y-m-d'));

        $second = $recurring->createNextEvent();
        self::assertSame('2025-01-20', $second->startDate->format('Y-m-d'));

        $third = $recurring->createNextEvent();
        self::assertSame('2025-01-27', $third->startDate->format('Y-m-d'));
    }

    public function testCreateNextEventCopiesProperties(): void
    {
        $recurring = $this->createRecurringEvent('2 weeks');
        $recurring->description = 'A recurring event';
        $recurring->setMemberPrice(10.00);
        $recurring->guestsAllowed = true;
        $recurring->subscriberLimit = 15;

        $event = $recurring->createNextEvent();

        self::assertSame('A recurring event', $event->description);
        self::assertSame(10.00, $event->getMemberPrice());
        self::assertTrue($event->guestsAllowed);
        self::assertSame(15, $event->subscriberLimit);
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
        $recurring->startDate = new DateTimeImmutable('-3 weeks');

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
        $event->name = 'Child';
        $event->location = 'Here';
        $event->startDate = new DateTimeImmutable('+1 week');
        $event->endDate = $event->startDate->modify('+1 hour');

        $recurring->addEvent($event);
        self::assertCount(1, $recurring->getEvents());
        self::assertSame($recurring, $event->recurringEvent);

        $recurring->removeEvent($event);
        self::assertCount(0, $recurring->getEvents());
        self::assertNull($event->recurringEvent);
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
        $event->startDate = new DateTimeImmutable('2025-01-15 19:00:00');

        $first = $event->createNextEvent();
        self::assertSame('2025-02-15', $first->startDate->format('Y-m-d'));

        $second = $event->createNextEvent();
        self::assertSame('2025-03-15', $second->startDate->format('Y-m-d'));
    }

    // --- copyFrom ---

    public function testCreateNextEventCopiesDuration(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $recurring->endDate = $recurring->startDate->modify('+3 hours');

        $event = $recurring->createNextEvent();

        self::assertSame(3, $event->getDuration()->h);
    }

    public function testCreateNextEventCopiesImage(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $image = new Image();
        $recurring->image = $image;

        $event = $recurring->createNextEvent();

        self::assertSame($image, $event->image);
    }

    public function testCreateNextEventCopiesTags(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $tag = new Tag();
        $tag->name = 'test';
        $recurring->addTag($tag);

        $event = $recurring->createNextEvent();

        self::assertCount(1, $event->getTags());
        self::assertTrue($event->getTags()->contains($tag));
    }

    public function testCreateNextEventCopiesNullGuestPrice(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        // guestPrice defaults to null — copyFrom should not crash
        self::assertNull($recurring->getGuestPrice());

        $event = $recurring->createNextEvent();

        self::assertNull($event->getGuestPrice());
    }

    public function testCreateNextEventCopiesGuestPrice(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $recurring->setGuestPrice(5.50);

        $event = $recurring->createNextEvent();

        self::assertSame(5.50, $event->getGuestPrice());
    }

    public function testCopyFromDoesNotCopySubscriptionOpenDate(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $recurring->subscriptionOpenDate = new DateTimeImmutable('2025-06-01');

        $event = $recurring->createNextEvent();

        // copyFrom does not include subscriptionOpenDate
        self::assertNull($event->subscriptionOpenDate);
    }

    public function testCopyFromCopiesSubscriptionDeadline(): void
    {
        $deadline = new DateTimeImmutable('2025-12-31');
        $recurring = $this->createRecurringEvent('1 week');
        $recurring->subscriptionDeadline = $deadline;

        $event = $recurring->createNextEvent();

        self::assertSame($deadline, $event->subscriptionDeadline);
    }

    // --- getPastEvents ---

    public function testGetPastEvents(): void
    {
        $recurring = $this->createRecurringEvent('1 week');
        $recurring->startDate = new DateTimeImmutable('-3 weeks');

        $recurring->createNextEvent(); // -2 weeks
        $recurring->createNextEvent(); // -1 week
        $recurring->createNextEvent(); // now-ish
        $recurring->createNextEvent(); // +1 week

        $past = $recurring->getPastEvents();
        self::assertGreaterThanOrEqual(2, $past->count());
    }

    // --- getRecurringDate fallback on invalid rule ---

    public function testGetRecurringDateFallsBackOnInvalidRule(): void
    {
        $recurring = $this->createRecurringEvent('invalid rule');
        $result = $recurring->getRecurringDate(1);

        // On invalid rule, getRecurringDate catches the exception and returns the input date
        self::assertSame($recurring->startDate->format('Y-m-d'), $result->format('Y-m-d'));
    }

    // --- validateRecurrenceRule ---

    public function testValidateRecurrenceRuleWithValidRule(): void
    {
        $recurring = $this->createRecurringEvent('1 week');

        $context = $this->createMock(\Symfony\Component\Validator\Context\ExecutionContextInterface::class);
        $context->method('getObject')->willReturn($recurring);
        $context->expects(self::never())->method('buildViolation');

        RecurringEvent::validateRecurrenceRule($recurring->recurrenceRule, $context, null);
    }

    public function testValidateRecurrenceRuleWithInvalidRule(): void
    {
        $recurring = $this->createRecurringEvent('not a valid interval');

        $context = $this->createMock(\Symfony\Component\Validator\Context\ExecutionContextInterface::class);
        $context->method('getObject')->willReturn($recurring);

        $violationBuilder = $this->createMock(\Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface::class);
        $violationBuilder->method('atPath')->willReturnSelf();
        $violationBuilder->expects(self::once())->method('addViolation');

        $context->expects(self::once())->method('buildViolation')
            ->with('Invalid recurrence rule')
            ->willReturn($violationBuilder);

        RecurringEvent::validateRecurrenceRule($recurring->recurrenceRule, $context, null);
    }
}
