<?php

namespace App\Tests\Entity\Event;

use App\Entity\Event\Event;
use App\Entity\Event\RecurringEvent;
use App\Entity\Event\Tag;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class RecurringEventTest extends TestCase
{
    private function createRecurringEvent(string $rule = 'FREQ=WEEKLY'): RecurringEvent
    {
        $event = new RecurringEvent();
        $event->name = 'Weekly Event';
        $event->location = 'Club';
        $event->startDate = new DateTimeImmutable('2025-01-06 18:00:00');
        $event->endDate = $event->startDate->modify('+2 hours');
        $event->rrule = $rule;

        return $event;
    }

    private function createEvent(string $start): Event
    {
        $event = new Event();
        $event->startDate = new DateTimeImmutable($start);
        $event->endDate = $event->startDate->modify('+1 hour');

        return $event;
    }

    // --- Template ---

    public function testEndForKeepsTheDuration(): void
    {
        $series = $this->createRecurringEvent();

        self::assertSame('2025-03-03 20:00', $series->endFor(new DateTimeImmutable('2025-03-03 18:00'))->format('Y-m-d H:i'));
    }

    public function testSubscriptionDatesAreRelativeToTheStart(): void
    {
        $series = $this->createRecurringEvent();
        $start = new DateTimeImmutable('2025-03-03 18:00');

        self::assertNull($series->subscriptionOpenFor($start));
        self::assertNull($series->subscriptionDeadlineFor($start));

        $series->subscriptionOpenOffset = 2 * 10080;
        $series->subscriptionDeadlineOffset = 1440;
        self::assertSame('2025-02-17 18:00', $series->subscriptionOpenFor($start)->format('Y-m-d H:i'));
        self::assertSame('2025-03-02 18:00', $series->subscriptionDeadlineFor($start)->format('Y-m-d H:i'));
    }

    public function testCopyTemplateFrom(): void
    {
        $series = $this->createRecurringEvent();
        $other = $this->createRecurringEvent('FREQ=MONTHLY;BYDAY=1SA');
        $other->name = 'Bouwdag';
        $other->addTag(new Tag());
        $other->setGuestPrice(7.5);
        $other->subscriptionDeadlineOffset = 60;
        $other->published = false;

        $series->copyTemplateFrom($other);

        self::assertSame('Bouwdag', $series->name);
        self::assertSame('FREQ=MONTHLY;BYDAY=1SA', $series->rrule);
        self::assertSame(7.5, $series->getGuestPrice());
        self::assertSame(60, $series->subscriptionDeadlineOffset);
        self::assertFalse($series->published);
        self::assertCount(1, $series->getTags());
        self::assertNotSame($other->getTags(), $series->getTags());
    }

    public function testCloneIsADetachedDraft(): void
    {
        $series = $this->createRecurringEvent();
        $series->addTag(new Tag());
        $series->addEvent($this->createEvent('2025-01-06 18:00'));

        $draft = clone $series;
        $draft->addTag(new Tag());

        self::assertNull($draft->id);
        self::assertCount(0, $draft->getEvents());
        self::assertCount(1, $series->getTags());
        self::assertCount(2, $draft->getTags());
    }

    // --- Exception dates ---

    public function testExcludeStoresTheDateOnce(): void
    {
        $series = $this->createRecurringEvent();
        $series->exclude(new DateTimeImmutable('2025-02-03 18:00'));
        $series->exclude(new DateTimeImmutable('2025-01-13 18:00'));
        $series->exclude(new DateTimeImmutable('2025-02-03 10:00'));

        self::assertSame(['2025-01-13', '2025-02-03'], $series->exceptionDates);
        self::assertTrue($series->isExcluded(new DateTimeImmutable('2025-02-03 18:00')));
        self::assertFalse($series->isExcluded(new DateTimeImmutable('2025-02-10 18:00')));
    }

    // --- Validation ---

    public function testValidRule(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertCount(0, $validator->validate($this->createRecurringEvent('FREQ=MONTHLY;INTERVAL=2;BYDAY=1MO')));
    }

    public function testInvalidRule(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate($this->createRecurringEvent('FREQ=WEEKLY;BYDAY=XX'));

        self::assertCount(1, $violations);
        self::assertSame('rrule', $violations[0]->getPropertyPath());
    }

    public function testSubDailyRulesAreRejected(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertCount(1, $validator->validate($this->createRecurringEvent('FREQ=HOURLY')));
    }

    // --- Events Collection ---

    public function testAddEventIsIdempotentAndLinksBack(): void
    {
        $series = $this->createRecurringEvent();
        $event = $this->createEvent('2025-01-06 18:00');

        $series->addEvent($event);
        $series->addEvent($event);

        self::assertCount(1, $series->getEvents());
        self::assertSame($series, $event->recurringEvent);
    }

    public function testRemoveEventUnlinks(): void
    {
        $series = $this->createRecurringEvent();
        $event = $this->createEvent('2025-01-06 18:00');
        $series->addEvent($event);

        $series->removeEvent($event);

        self::assertCount(0, $series->getEvents());
        self::assertNull($event->recurringEvent);
    }

    public function testFutureAndPastEvents(): void
    {
        $series = $this->createRecurringEvent();
        $past = $this->createEvent('-1 week');
        $future = $this->createEvent('+1 week');
        $series->addEvent($past);
        $series->addEvent($future);

        self::assertSame([$future], array_values($series->getFutureEvents()->toArray()));
        self::assertSame([$past], array_values($series->getPastEvents()->toArray()));
    }
}
