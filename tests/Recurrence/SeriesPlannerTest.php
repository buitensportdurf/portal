<?php

namespace App\Tests\Recurrence;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\Event\RecurringEvent;
use App\Entity\User;
use App\Recurrence\Plan\ChangeType;
use App\Recurrence\Plan\OccurrenceChange;
use App\Recurrence\SeriesField;
use App\Recurrence\SeriesPlanner;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SeriesPlannerTest extends TestCase
{
    private DateTimeImmutable $now;
    private SeriesPlanner $planner;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-10-12 12:00');
        $this->planner = new SeriesPlanner();
    }

    /**
     * First Saturday of the month, 10:00-15:00, with occurrences generated for the next 12 months.
     */
    private function createSeries(string $rule = 'FREQ=MONTHLY;BYDAY=1SA'): RecurringEvent
    {
        $series = new RecurringEvent();
        $series->name = 'Bouwdag';
        $series->location = 'Clubhuis';
        $series->startDate = new DateTimeImmutable('2024-03-02 10:00');
        $series->endDate = new DateTimeImmutable('2024-03-02 15:00');
        $series->rrule = $rule;

        foreach ($this->planner->planExtension($series, $this->now)->occurrences as $change) {
            $event = new Event();
            $event->occurrenceStart = $change->slot;
            foreach (SeriesField::cases() as $field) {
                $field->apply($series, $change->slot, $event);
            }
            $series->addEvent($event);
        }

        return $series;
    }

    private function subscribe(Event $event, bool $wantsChanges = true): void
    {
        $subscription = new EventSubscription();
        $subscription->amount = 1;
        $subscription->createdUser = new User();
        $subscription->createdUser->email = 'subscriber@example.com';
        if (!$wantsChanges) {
            $subscription->createdUser->emailNotifications = [];
        }
        $event->addEventSubscription($subscription);
    }

    private function eventOn(RecurringEvent $series, string $date): Event
    {
        foreach ($series->getEvents() as $event) {
            if ($event->occurrenceStart->format('Y-m-d') === $date) {
                return $event;
            }
        }
        self::fail("No occurrence on $date");
    }

    /**
     * @param list<OccurrenceChange> $changes
     * @return list<string>
     */
    private static function dates(array $changes): array
    {
        return array_map(fn(OccurrenceChange $change) => $change->slot->format('Y-m-d'), $changes);
    }

    public function testExtensionCreatesOccurrencesUpToHorizon(): void
    {
        $series = $this->createSeries();

        self::assertCount(12, $series->getEvents());
        self::assertSame('2026-11-07 10:00', $series->getEvents()->first()->startDate->format('Y-m-d H:i'));
        self::assertSame('2026-11-07 15:00', $series->getEvents()->first()->endDate->format('Y-m-d H:i'));
    }

    public function testExtensionOnlyAddsMissingOccurrences(): void
    {
        $series = $this->createSeries();

        self::assertSame([], $this->planner->planExtension($series, $this->now)->occurrences);
        self::assertSame(
            ['2027-11-06'],
            self::dates($this->planner->planExtension($series, $this->now->modify('+1 month'))->occurrences),
        );
    }

    public function testExtensionSkipsExceptionDatesAndRescheduledOccurrences(): void
    {
        $series = $this->createSeries();
        $series->exclude(new DateTimeImmutable('2027-11-06'));
        $this->eventOn($series, '2026-12-05')->startDate = new DateTimeImmutable('2026-12-12 10:00');

        self::assertSame([], $this->planner->planExtension($series, $this->now->modify('+1 month'))->occurrences);
    }

    public function testUnchangedDraftPlansNothing(): void
    {
        $series = $this->createSeries();

        $plan = $this->planner->plan($series, clone $series, now: $this->now);

        self::assertTrue($plan->isEmpty());
        self::assertSame(12, $plan->count(ChangeType::Unchanged));
    }

    public function testNameChangeUpdatesOccurrencesButKeepsCustomisedOnes(): void
    {
        $series = $this->createSeries();
        $custom = $this->eventOn($series, '2027-01-02');
        $custom->name = 'Nieuwjaarsbouwdag';
        $custom->markOverridden(SeriesField::Name);

        $draft = clone $series;
        $draft->name = 'Klusdag';
        $plan = $this->planner->plan($series, $draft, now: $this->now);

        self::assertSame([['label' => 'Name', 'old' => 'Bouwdag', 'new' => 'Klusdag']], $plan->seriesChanges);
        self::assertSame(11, $plan->count(ChangeType::Update));
        self::assertSame(['2027-01-02'], self::dates($plan->keptOverrides()));
        self::assertSame([SeriesField::Name], $plan->keptOverrides()[0]->keptOverrides);
        self::assertSame([], $plan->notifications());
    }

    public function testTimeChangeMovesOccurrencesAndNotifiesSubscribers(): void
    {
        $series = $this->createSeries();
        $this->subscribe($this->eventOn($series, '2026-11-07'));

        $draft = clone $series;
        $draft->startDate = $draft->startDate->setTime(11, 0);
        $draft->endDate = $draft->endDate->setTime(16, 0);
        $plan = $this->planner->plan($series, $draft, now: $this->now);

        self::assertSame(12, $plan->count(ChangeType::Move));
        self::assertSame(0, $plan->count(ChangeType::Create) + $plan->count(ChangeType::Delete));
        self::assertSame(['2026-11-07'], self::dates($plan->notifications()));
        $move = $plan->notifications()[0]->change(SeriesField::Schedule);
        self::assertSame('Sat 7 Nov 2026 10:00–15:00', $move->oldText());
        self::assertSame('Sat 7 Nov 2026 11:00–16:00', $move->newText());
    }

    public function testWeekdayChangeMovesEachOccurrenceWithinItsMonth(): void
    {
        $series = $this->createSeries();

        $draft = clone $series;
        $draft->rrule = 'FREQ=MONTHLY;BYDAY=1SU';
        $plan = $this->planner->plan($series, $draft, now: $this->now);

        self::assertSame(12, $plan->count(ChangeType::Move));
        $first = $plan->ofType(ChangeType::Move)[0];
        self::assertSame('2026-11-07', $first->event->occurrenceStart->format('Y-m-d'));
        self::assertSame('2026-11-01', $first->slot->format('Y-m-d'));
        self::assertSame(
            ['label' => 'Repeats', 'old' => 'monthly on the first Saturday of the month', 'new' => 'monthly on the first Sunday of the month'],
            $plan->seriesChanges[0],
        );
    }

    public function testRescheduledOccurrenceKeepsItsDateWhenTheRuleShifts(): void
    {
        $series = $this->createSeries();
        $custom = $this->eventOn($series, '2026-12-05');
        $custom->startDate = new DateTimeImmutable('2026-12-12 10:00');
        $custom->endDate = new DateTimeImmutable('2026-12-12 15:00');
        $custom->markOverridden(SeriesField::Schedule);

        $draft = clone $series;
        $draft->rrule = 'FREQ=MONTHLY;BYDAY=1SU';
        $plan = $this->planner->plan($series, $draft, now: $this->now);

        $change = array_find($plan->occurrences, fn(OccurrenceChange $change) => $change->event === $custom);
        self::assertSame(ChangeType::Unchanged, $change->type);
        self::assertSame('2026-12-06', $change->slot->format('Y-m-d'));
        self::assertSame([SeriesField::Schedule], $change->keptOverrides);
    }

    public function testFewerOccurrencesDeletesEmptyOnesAndCancelsSubscribedOnes(): void
    {
        $series = $this->createSeries();
        $this->subscribe($this->eventOn($series, '2026-12-05'));

        $draft = clone $series;
        $draft->rrule = 'FREQ=MONTHLY;INTERVAL=2;BYDAY=1SA';
        $draft->startDate = new DateTimeImmutable('2024-03-02 10:00');
        $plan = $this->planner->plan($series, $draft, now: $this->now);

        self::assertSame(['2026-12-05'], self::dates($plan->ofType(ChangeType::Cancel)));
        self::assertSame(5, $plan->count(ChangeType::Delete));
        self::assertSame(6, $plan->count(ChangeType::Unchanged));
        self::assertSame(1, $plan->notifiedSubscriberCount());
    }

    public function testSubscribersWhoSwitchedChangeEmailsOffAreNotNotified(): void
    {
        $series = $this->createSeries();
        $event = $this->eventOn($series, '2026-12-05');
        $this->subscribe($event);
        $this->subscribe($event, wantsChanges: false);

        $draft = clone $series;
        $draft->location = 'Somewhere else';
        $plan = $this->planner->plan($series, $draft, now: $this->now);

        self::assertSame(1, $plan->notifiedSubscriberCount());
    }

    public function testExceptionDateFollowsTheRule(): void
    {
        $series = $this->createSeries();
        $series->exclude(new DateTimeImmutable('2027-02-06'));
        $series->removeEvent($this->eventOn($series, '2027-02-06'));

        $draft = clone $series;
        $draft->rrule = 'FREQ=MONTHLY;BYDAY=1SU';
        $plan = $this->planner->plan($series, $draft, now: $this->now);

        self::assertSame(['2027-02-07'], $plan->exceptionDates);
        self::assertSame(0, $plan->count(ChangeType::Create));
    }

    public function testSplitOnlyTouchesOccurrencesFromTheSplitDate(): void
    {
        $series = $this->createSeries();

        $draft = clone $series;
        $draft->location = 'Strand';
        $plan = $this->planner->plan($series, $draft, new DateTimeImmutable('2027-03-01'), $this->now);

        self::assertSame('2027-03-01', $plan->splitAt->format('Y-m-d'));
        self::assertSame(
            ['2027-03-06', '2027-04-03', '2027-05-01', '2027-06-05', '2027-07-03', '2027-08-07', '2027-09-04', '2027-10-02'],
            self::dates($plan->ofType(ChangeType::Update)),
        );
        self::assertCount(8, $plan->occurrences);
    }

    public function testRemovalCancelsSubscribedOccurrences(): void
    {
        $series = $this->createSeries();
        $this->subscribe($this->eventOn($series, '2026-11-07'));

        $plan = $this->planner->planRemoval($series, $this->now);

        self::assertTrue($plan->removesSeries);
        self::assertSame(['2026-11-07'], self::dates($plan->ofType(ChangeType::Cancel)));
        self::assertSame(11, $plan->count(ChangeType::Delete));
    }

    public function testCancelledAndPastOccurrencesAreLeftAlone(): void
    {
        $series = $this->createSeries();
        $past = new Event();
        $past->startDate = new DateTimeImmutable('2026-10-03 10:00');
        $past->endDate = new DateTimeImmutable('2026-10-03 15:00');
        $past->occurrenceStart = $past->startDate;
        $series->addEvent($past);
        $cancelled = $this->eventOn($series, '2026-11-07');
        $cancelled->cancelledAt = $this->now;
        $cancelled->occurrenceStart = null;
        $series->exclude(new DateTimeImmutable('2026-11-07'));

        $draft = clone $series;
        $draft->name = 'Klusdag';
        $plan = $this->planner->plan($series, $draft, now: $this->now);

        $touched = array_map(fn(OccurrenceChange $change) => $change->event, $plan->occurrences);
        self::assertNotContains($past, $touched);
        self::assertNotContains($cancelled, $touched);
        self::assertSame(11, $plan->count(ChangeType::Update));
    }
}
