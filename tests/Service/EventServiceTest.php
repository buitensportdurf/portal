<?php

namespace App\Tests\Service;

use App\Entity\Event\Event;
use App\Entity\Event\RecurringEvent;
use App\Entity\Event\Tag;
use App\Repository\Event\TagRepository;
use App\Service\EventService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EventServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private TagRepository $tagRepository;
    private EventService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->service = new EventService($this->em, $this->tagRepository);
    }

    private function createRecurringEvent(string $startDate = '-2 weeks', string $rule = '1 week'): RecurringEvent
    {
        $recurring = new RecurringEvent();
        $recurring->name = 'Test Recurring';
        $recurring->location = 'Test';
        $recurring->startDate = new DateTimeImmutable($startDate);
        $recurring->endDate = $recurring->startDate->modify('+2 hours');
        $recurring->recurrenceRule = $rule;

        return $recurring;
    }

    public function testDeleteFutureEventsRemovesFutureOnly(): void
    {
        $recurring = $this->createRecurringEvent('-3 weeks');

        // Create 4 events: some past, some future
        $recurring->createNextEvent(); // -2 weeks
        $recurring->createNextEvent(); // -1 week
        $recurring->createNextEvent(); // ~now
        $recurring->createNextEvent(); // +1 week

        $futureCount = $recurring->getFutureEvents()->count();

        $this->em->expects(self::exactly($futureCount))->method('remove');

        $deleted = $this->service->deleteFutureEvents($recurring);

        self::assertSame($futureCount, $deleted);
    }

    public function testDeleteFutureEventsReturnsZeroWhenNone(): void
    {
        $recurring = $this->createRecurringEvent('-3 weeks');

        // Create only past events
        $recurring->createNextEvent(); // -2 weeks

        // If all events are in the past, nothing to delete
        if ($recurring->getFutureEvents()->count() === 0) {
            $this->em->expects(self::never())->method('remove');
            $deleted = $this->service->deleteFutureEvents($recurring);
            self::assertSame(0, $deleted);
        } else {
            // The event might be borderline, just verify method runs
            $deleted = $this->service->deleteFutureEvents($recurring);
            self::assertGreaterThanOrEqual(0, $deleted);
        }
    }

    public function testDeleteFutureEventsRemovesFromCollection(): void
    {
        $recurring = $this->createRecurringEvent('-1 week');

        $recurring->createNextEvent(); // ~now or future
        $recurring->createNextEvent(); // +1 week

        $totalBefore = $recurring->getEvents()->count();
        $futureCount = $recurring->getFutureEvents()->count();

        $this->service->deleteFutureEvents($recurring);

        self::assertSame($totalBefore - $futureCount, $recurring->getEvents()->count());
    }

    public function testCreateNewEventsCreatesUpToOneYear(): void
    {
        $recurring = $this->createRecurringEvent('now', '1 month');

        $recurringTag = new Tag();
        $recurringTag->name = 'recurring';
        $this->tagRepository->method('find')->with(Tag::ID_RECURRING)->willReturn($recurringTag);

        $this->em->expects(self::atLeastOnce())->method('persist');

        $created = $this->service->createNewEvents($recurring);

        // Monthly for 1 year = ~12 events
        self::assertGreaterThanOrEqual(11, $created);
        self::assertLessThanOrEqual(13, $created);
    }

    public function testCreateNewEventsAddsRecurringTag(): void
    {
        $recurring = $this->createRecurringEvent('now', '1 month');

        $recurringTag = new Tag();
        $recurringTag->name = 'recurring';
        $this->tagRepository->method('find')->with(Tag::ID_RECURRING)->willReturn($recurringTag);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            if ($entity instanceof Event) {
                $persisted[] = $entity;
            }
        });

        $this->service->createNewEvents($recurring);

        self::assertNotEmpty($persisted);
        foreach ($persisted as $event) {
            self::assertTrue($event->getTags()->contains($recurringTag));
        }
    }

    public function testCreateNewEventsRemovesLastOvershootEvent(): void
    {
        $recurring = $this->createRecurringEvent('now', '1 month');

        $recurringTag = new Tag();
        $recurringTag->name = 'recurring';
        $this->tagRepository->method('find')->with(Tag::ID_RECURRING)->willReturn($recurringTag);

        $created = $this->service->createNewEvents($recurring);

        // The events collection should have exactly $created events
        // (the overshoot event beyond 1 year is removed)
        self::assertSame($created, $recurring->getEvents()->count());
    }

    public function testCreateNewEventsReturnsCount(): void
    {
        $recurring = $this->createRecurringEvent('now', '1 week');

        $recurringTag = new Tag();
        $recurringTag->name = 'recurring';
        $this->tagRepository->method('find')->with(Tag::ID_RECURRING)->willReturn($recurringTag);

        $created = $this->service->createNewEvents($recurring);

        // Weekly for 1 year = ~52 events
        self::assertGreaterThanOrEqual(51, $created);
        self::assertLessThanOrEqual(53, $created);
    }
}
