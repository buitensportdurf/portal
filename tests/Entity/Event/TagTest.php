<?php

namespace App\Tests\Entity\Event;

use App\Entity\Event\Event;
use App\Entity\Event\RecurringEvent;
use App\Entity\Event\Tag;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    public function testSetAndGetName(): void
    {
        $tag = new Tag();
        $tag->name = 'climbing';
        self::assertSame('climbing', $tag->name);
    }

    public function testToStringFormatsName(): void
    {
        $tag = new Tag();
        $tag->name = 'rock_climbing';
        self::assertSame('Rock climbing', (string) $tag);
    }

    public function testToStringCapitalizesFirstLetter(): void
    {
        $tag = new Tag();
        $tag->name = 'hiking';
        self::assertSame('Hiking', (string) $tag);
    }

    public function testDefaultHideFalseByDefault(): void
    {
        $tag = new Tag();
        self::assertFalse($tag->defaultHide);
    }

    public function testSetDefaultHide(): void
    {
        $tag = new Tag();
        $tag->defaultHide = true;
        self::assertTrue($tag->defaultHide);
    }

    public function testEventsEmptyByDefault(): void
    {
        $tag = new Tag();
        self::assertCount(0, $tag->getEvents());
    }

    public function testAddEventBidirectional(): void
    {
        $tag = new Tag();
        $tag->name = 'test';

        $event = new Event();
        $event->name = 'Event';
        $event->location = 'Here';
        $event->startDate = new DateTimeImmutable('+1 week');
        $event->duration = new DateInterval('PT1H');

        $tag->addEvent($event);

        self::assertCount(1, $tag->getEvents());
        self::assertTrue($event->getTags()->contains($tag));
    }

    public function testRemoveEventBidirectional(): void
    {
        $tag = new Tag();
        $tag->name = 'test';

        $event = new Event();
        $event->name = 'Event';
        $event->location = 'Here';
        $event->startDate = new DateTimeImmutable('+1 week');
        $event->duration = new DateInterval('PT1H');

        $tag->addEvent($event);
        $tag->removeEvent($event);

        self::assertCount(0, $tag->getEvents());
        self::assertFalse($event->getTags()->contains($tag));
    }

    public function testAddEventIdempotent(): void
    {
        $tag = new Tag();
        $tag->name = 'test';

        $event = new Event();
        $event->name = 'Event';
        $event->location = 'Here';
        $event->startDate = new DateTimeImmutable('+1 week');
        $event->duration = new DateInterval('PT1H');

        $tag->addEvent($event);
        $tag->addEvent($event);

        self::assertCount(1, $tag->getEvents());
    }

    public function testRecurringEventsEmptyByDefault(): void
    {
        $tag = new Tag();
        self::assertCount(0, $tag->getRecurringEvents());
    }

    public function testAddRecurringEventBidirectional(): void
    {
        $tag = new Tag();
        $tag->name = 'test';

        $recurring = new RecurringEvent();
        $recurring->name = 'Recurring';
        $recurring->location = 'Here';
        $recurring->startDate = new DateTimeImmutable('+1 week');
        $recurring->duration = new DateInterval('PT1H');
        $recurring->recurrenceRule = '1 week';

        $tag->addRecurringEvent($recurring);

        self::assertCount(1, $tag->getRecurringEvents());
        self::assertTrue($recurring->getTags()->contains($tag));
    }

    public function testRemoveRecurringEventBidirectional(): void
    {
        $tag = new Tag();
        $tag->name = 'test';

        $recurring = new RecurringEvent();
        $recurring->name = 'Recurring';
        $recurring->location = 'Here';
        $recurring->startDate = new DateTimeImmutable('+1 week');
        $recurring->duration = new DateInterval('PT1H');
        $recurring->recurrenceRule = '1 week';

        $tag->addRecurringEvent($recurring);
        $tag->removeRecurringEvent($recurring);

        self::assertCount(0, $tag->getRecurringEvents());
        self::assertFalse($recurring->getTags()->contains($tag));
    }

    public function testIdRecurringConstant(): void
    {
        self::assertSame(1, Tag::ID_RECURRING);
    }
}
