<?php

namespace App\Recurrence;

use App\Entity\Event\Event;
use App\Entity\Event\RecurringEvent;
use App\Recurrence\Plan\ChangeType;
use App\Recurrence\Plan\SeriesPlan;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Carries out a SeriesPlan and flushes it.
 */
readonly class SeriesSynchronizer
{
    public function __construct(
        private EntityManagerInterface $em,
        private SubscriberNotifier     $notifier,
    ) {}

    /**
     * @return RecurringEvent the series the occurrences now belong to (a new one after a split)
     */
    public function apply(SeriesPlan $plan, bool $notify = true): RecurringEvent
    {
        $series = $plan->series;
        $owner = match (true) {
            $plan->splitAt !== null => $this->split($plan),
            $plan->target !== $series => $this->update($plan),
            default => $series,
        };

        $now = new DateTimeImmutable();
        foreach ($plan->occurrences as $change) {
            $event = $change->event;
            match ($change->type) {
                ChangeType::Create => $this->create($owner, $change->slot),
                ChangeType::Move, ChangeType::Update, ChangeType::Unchanged => $this->reassign($owner, $event, $change->slot, $change->changes),
                ChangeType::Delete => $this->delete($event),
                ChangeType::Cancel => self::cancel($event, $now),
            };
        }

        if ($plan->removesSeries) {
            foreach ($series->getEvents()->toArray() as $event) {
                $series->removeEvent($event);
            }
            $this->em->remove($series);
        }

        $this->em->flush();

        if ($notify) {
            foreach ($plan->notifications() as $change) {
                $this->notifier->notify($change);
            }
        }

        return $owner;
    }

    /**
     * Cancels an occurrence: it stays visible (with its subscribers) but leaves the series' slot
     * logic, and its slot is excluded so generation does not bring it back.
     */
    public static function cancel(Event $event, ?DateTimeImmutable $now = null): void
    {
        $event->cancelledAt = $now ?? new DateTimeImmutable();
        self::release($event);
    }

    /**
     * Frees the slot of an occurrence that is about to be deleted or detached, so generation does
     * not recreate it.
     */
    public static function release(Event $event): void
    {
        if ($event->recurringEvent !== null && $event->occurrenceStart !== null) {
            $event->recurringEvent->exclude($event->occurrenceStart);
        }
        $event->occurrenceStart = null;
    }

    private function split(SeriesPlan $plan): RecurringEvent
    {
        $series = $plan->series;
        $target = $plan->target;

        $slots = array_map(
            fn($change) => $change->slot,
            $plan->ofType(ChangeType::Create, ChangeType::Move, ChangeType::Update, ChangeType::Unchanged),
        );
        if ($slots !== []) {
            $first = min($slots);
            $duration = $target->getDuration();
            $target->startDate = $first;
            $target->endDate = $duration ? $first->add($duration) : $first;
        }
        $target->exceptionDates = $plan->exceptionDates;
        $this->em->persist($target);

        $parts = OccurrenceCalculator::parse($series->rrule);
        unset($parts['COUNT']);
        $parts['UNTIL'] = $plan->splitAt->modify('-1 day')->format('Ymd');
        $series->rrule = OccurrenceCalculator::compose($parts);

        return $target;
    }

    private function update(SeriesPlan $plan): RecurringEvent
    {
        $plan->series->copyTemplateFrom($plan->target);
        $plan->series->exceptionDates = $plan->exceptionDates;

        return $plan->series;
    }

    private function create(RecurringEvent $owner, DateTimeImmutable $slot): void
    {
        $event = new Event();
        $event->occurrenceStart = $slot;
        foreach (SeriesField::cases() as $field) {
            $field->apply($owner, $slot, $event);
        }
        $owner->addEvent($event);
        $this->em->persist($event);
    }

    /**
     * @param list<\App\Recurrence\Plan\FieldChange> $changes
     */
    private function reassign(RecurringEvent $owner, Event $event, DateTimeImmutable $slot, array $changes): void
    {
        if ($event->recurringEvent !== $owner) {
            $event->recurringEvent?->removeEvent($event);
            $owner->addEvent($event);
        }
        $event->occurrenceStart = $slot;
        // SeriesField lists Schedule first, so dependent fields (subscription dates) see the new start.
        foreach ($changes as $change) {
            $change->field->apply($owner, $slot, $event);
        }
    }

    private function delete(Event $event): void
    {
        $event->recurringEvent?->removeEvent($event);
        $this->em->remove($event);
    }
}
