<?php

namespace App\Tests\Recurrence;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\Event\RecurringEvent;
use App\Entity\User;
use App\Recurrence\Plan\ChangeType;
use App\Recurrence\SeriesField;
use App\Recurrence\SeriesPlanner;
use App\Recurrence\SeriesSynchronizer;
use App\Recurrence\SubscriberNotifier;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SeriesSynchronizerTest extends TestCase
{
    private SeriesPlanner $planner;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->planner = new SeriesPlanner();
        $this->now = new DateTimeImmutable('+1 hour');
    }

    private function createSeries(): RecurringEvent
    {
        $series = new RecurringEvent();
        $series->name = 'Training';
        $series->location = 'Hal';
        $series->startDate = new DateTimeImmutable('monday next week 19:00');
        $series->endDate = $series->startDate->modify('+90 minutes');
        $series->rrule = 'FREQ=WEEKLY';
        $series->subscriptionDeadlineOffset = 1440;

        return $series;
    }

    private function synchronizer(?EntityManagerInterface $em = null, ?SubscriberNotifier $notifier = null): SeriesSynchronizer
    {
        return new SeriesSynchronizer(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $notifier ?? $this->createStub(SubscriberNotifier::class),
        );
    }

    public function testCreatesOccurrencesFromTheTemplate(): void
    {
        $series = $this->createSeries();
        $plan = $this->planner->planExtension($series, $this->now);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(count($plan->occurrences)))->method('persist');
        $em->expects(self::once())->method('flush');

        $this->synchronizer($em)->apply($plan);

        $first = $series->getEvents()->first();
        self::assertEquals($series->startDate, $first->startDate);
        self::assertEquals($series->endDate, $first->endDate);
        self::assertEquals($series->startDate->modify('-1 day'), $first->subscriptionDeadline);
        self::assertSame('Training', $first->name);
        self::assertEquals($series->startDate, $first->occurrenceStart);
    }

    public function testUpdateCopiesTheDraftOntoTheSeries(): void
    {
        $series = $this->createSeries();
        $this->synchronizer()->apply($this->planner->planExtension($series, $this->now));

        $draft = clone $series;
        $draft->location = 'Buiten';
        $owner = $this->synchronizer()->apply($this->planner->plan($series, $draft, now: $this->now));

        self::assertSame($series, $owner);
        self::assertSame('Buiten', $series->location);
        self::assertSame('Buiten', $series->getEvents()->last()->location);
    }

    public function testSplitEndsTheSeriesAndMovesLaterOccurrencesToTheNewOne(): void
    {
        $series = $this->createSeries();
        $this->synchronizer()->apply($this->planner->planExtension($series, $this->now));
        $total = $series->getEvents()->count();
        $splitAt = $series->startDate->modify('+4 weeks');

        $draft = clone $series;
        $draft->location = 'Buiten';
        $owner = $this->synchronizer()->apply($this->planner->plan($series, $draft, $splitAt, $this->now));

        self::assertNotSame($series, $owner);
        self::assertSame('FREQ=WEEKLY;UNTIL=' . $splitAt->modify('-1 day')->format('Ymd'), $series->rrule);
        self::assertCount(4, $series->getEvents());
        self::assertSame('Hal', $series->location);
        self::assertSame($splitAt->format('Y-m-d H:i'), $owner->startDate->format('Y-m-d H:i'));
        self::assertSame('Buiten', $owner->getEvents()->first()->location);
        self::assertCount($total - 4, $owner->getEvents());
    }

    public function testRemovalCancelsSubscribedOccurrencesAndNotifies(): void
    {
        $series = $this->createSeries();
        $this->synchronizer()->apply($this->planner->planExtension($series, $this->now));
        $subscribed = $series->getEvents()->first();
        $subscription = new EventSubscription();
        $subscription->amount = 1;
        $subscription->createdUser = new User();
        $subscription->createdUser->email = 'subscriber@example.com';
        $subscribed->addEventSubscription($subscription);

        $plan = $this->planner->planRemoval($series, $this->now);
        $notifier = $this->createMock(SubscriberNotifier::class);
        $notifier->expects(self::once())->method('notify')->with($plan->ofType(ChangeType::Cancel)[0]);
        $em = $this->createMock(EntityManagerInterface::class);
        // every occurrence but the cancelled one, plus the series itself
        $em->expects(self::exactly($series->getEvents()->count()))->method('remove');

        $this->synchronizer($em, $notifier)->apply($plan);

        self::assertTrue($subscribed->isCancelled());
        self::assertNull($subscribed->occurrenceStart);
        self::assertNull($subscribed->recurringEvent);
        self::assertCount(0, $series->getEvents());
    }

    public function testReleaseExcludesTheSlot(): void
    {
        $series = $this->createSeries();
        $event = new Event();
        $event->occurrenceStart = $series->startDate;
        foreach (SeriesField::cases() as $field) {
            $field->apply($series, $series->startDate, $event);
        }
        $series->addEvent($event);

        SeriesSynchronizer::release($event);

        self::assertTrue($series->isExcluded($series->startDate));
        self::assertNull($event->occurrenceStart);
    }
}
