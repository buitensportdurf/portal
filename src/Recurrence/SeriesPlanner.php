<?php

namespace App\Recurrence;

use App\Entity\Event\Event;
use App\Entity\Event\RecurringEvent;
use App\Recurrence\Plan\ChangeType;
use App\Recurrence\Plan\FieldChange;
use App\Recurrence\Plan\OccurrenceChange;
use App\Recurrence\Plan\SeriesPlan;
use DateTimeImmutable;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

/**
 * Works out what a series change means for its occurrences, without changing anything.
 *
 * Only upcoming, non-cancelled occurrences are ever touched; the past stays as it happened.
 */
final class SeriesPlanner
{
    /**
     * How far ahead occurrences exist.
     */
    public const string HORIZON = '+12 months';

    /**
     * Plans applying $target (an edited copy of $series) to the series.
     *
     * @param DateTimeImmutable|null $from apply only to occurrences from this date on, splitting the
     *                                     series in two ("this and following"); null for all upcoming
     */
    public function plan(
        RecurringEvent     $series,
        RecurringEvent     $target,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $now = null,
    ): SeriesPlan
    {
        $now ??= new DateTimeImmutable();
        $from = $from !== null && $from > $now ? $from->setTime(0, 0) : null;
        $windowStart = $from ?? $now;

        // Upcoming occurrences in scope, and the exception dates in scope as "ghosts", so a deleted
        // occurrence stays deleted when the rule shifts.
        $items = [];
        $events = [];
        foreach ($series->getFutureEvents($now) as $event) {
            if ($event->isCancelled() || ($event->occurrenceStart ?? $event->startDate) < $windowStart) {
                continue;
            }
            $events[$event->id ?? spl_object_id($event)] = $event;
            $items['e' . ($event->id ?? spl_object_id($event))] = $event->occurrenceStart ?? $event->startDate;
        }
        $keptExceptionDates = [];
        foreach ($series->exceptionDates as $date) {
            $ghost = new DateTimeImmutable($date . ' ' . $series->startDate->format('H:i'));
            if ($ghost >= $windowStart) {
                $items['x' . $date] = $ghost;
            } elseif ($from === null) {
                $keptExceptionDates[] = $date;
            }
        }

        $probe = clone $target;
        $probe->exceptionDates = [];
        $slots = OccurrenceCalculator::between($probe, $windowStart, $now->modify(self::HORIZON));
        $match = OccurrenceMatcher::match($items, $slots);

        $occurrences = [];
        $exceptionDates = $keptExceptionDates;
        foreach ($match['pairs'] as $itemKey => $slotIndex) {
            $slot = $slots[$slotIndex];
            if ($itemKey[0] === 'x') {
                $exceptionDates[] = $slot->format('Y-m-d');
                continue;
            }
            $occurrences[] = $this->compare($series, $target, $events[substr($itemKey, 1)], $slot);
        }
        foreach ($match['unmatchedItems'] as $itemKey) {
            if ($itemKey[0] === 'e') {
                $occurrences[] = $this->removal($events[substr($itemKey, 1)]);
            }
        }
        foreach ($match['unmatchedSlots'] as $slotIndex) {
            $occurrences[] = new OccurrenceChange(ChangeType::Create, null, $slots[$slotIndex]);
        }
        usort($occurrences, fn(OccurrenceChange $a, OccurrenceChange $b) => $a->slot <=> $b->slot);
        sort($exceptionDates);

        return new SeriesPlan(
            series: $series,
            target: $target,
            splitAt: $from,
            seriesChanges: $series === $target ? [] : $this->seriesChanges($series, $target),
            occurrences: $occurrences,
            exceptionDates: array_values(array_unique($exceptionDates)),
        );
    }

    /**
     * Plans topping the series up to the horizon. Only adds missing occurrences, never changes
     * existing ones, so it is safe to run unattended.
     */
    public function planExtension(RecurringEvent $series, ?DateTimeImmutable $now = null): SeriesPlan
    {
        $now ??= new DateTimeImmutable();

        $existing = [];
        foreach ($series->getEvents() as $event) {
            if ($event->occurrenceStart !== null) {
                $existing[$event->occurrenceStart->format('Y-m-d')] = true;
            }
        }

        $occurrences = [];
        foreach (OccurrenceCalculator::between($series, $now, $now->modify(self::HORIZON)) as $slot) {
            if (!isset($existing[$slot->format('Y-m-d')])) {
                $occurrences[] = new OccurrenceChange(ChangeType::Create, null, $slot);
            }
        }

        return new SeriesPlan($series, $series, null, [], $occurrences, $series->exceptionDates);
    }

    /**
     * Plans deleting the series: upcoming occurrences go (or are cancelled when people subscribed),
     * past ones stay as standalone events.
     */
    public function planRemoval(RecurringEvent $series, ?DateTimeImmutable $now = null): SeriesPlan
    {
        $occurrences = [];
        foreach ($series->getFutureEvents($now ?? new DateTimeImmutable()) as $event) {
            if (!$event->isCancelled()) {
                $occurrences[] = $this->removal($event);
            }
        }

        return new SeriesPlan($series, $series, null, [], $occurrences, [], removesSeries: true);
    }

    private function compare(RecurringEvent $series, RecurringEvent $target, Event $event, DateTimeImmutable $slot): OccurrenceChange
    {
        $start = $event->isOverridden(SeriesField::Schedule) ? $event->startDate : $slot;
        $oldSlot = $event->occurrenceStart ?? $event->startDate;

        $changes = [];
        $keptOverrides = [];
        foreach (SeriesField::cases() as $field) {
            $new = $field->expected($target, $slot, $start);
            if ($event->isOverridden($field)) {
                if ($field->expected($series, $oldSlot, $start) !== $new) {
                    $keptOverrides[] = $field;
                }
                continue;
            }
            $old = $field->read($event);
            if ($old !== $new) {
                $changes[] = new FieldChange($field, $old, $new);
            }
        }

        $type = match (true) {
            array_any($changes, fn(FieldChange $change) => $change->field === SeriesField::Schedule) => ChangeType::Move,
            $changes !== [] => ChangeType::Update,
            default => ChangeType::Unchanged,
        };

        return new OccurrenceChange($type, $event, $slot, $changes, $keptOverrides);
    }

    private function removal(Event $event): OccurrenceChange
    {
        return new OccurrenceChange(
            $event->getEventSubscriptions()->isEmpty() ? ChangeType::Delete : ChangeType::Cancel,
            $event,
            $event->occurrenceStart ?? $event->startDate,
        );
    }

    /**
     * Compares the series settings themselves; values are compared raw and shown formatted.
     *
     * @return list<array{label: TranslatableMessage, old: string|TranslatableInterface, new: string|TranslatableInterface}>
     */
    private function seriesChanges(RecurringEvent $series, RecurringEvent $target): array
    {
        $schedule = SeriesField::Schedule;
        $rows = [
            [
                t('Repeats'),
                OccurrenceCalculator::describe($series),
                OccurrenceCalculator::describe($target),
                new RecurrenceDescription($series),
                new RecurrenceDescription($target),
            ],
            [
                t('First occurrence'),
                $old = $schedule->format($schedule->expected($series, $series->startDate, $series->startDate)),
                $new = $schedule->format($schedule->expected($target, $target->startDate, $target->startDate)),
                $old,
                $new,
            ],
            [
                t('Subscriptions open'),
                $series->subscriptionOpenOffset,
                $target->subscriptionOpenOffset,
                Offset::describe($series->subscriptionOpenOffset, t('right away')),
                Offset::describe($target->subscriptionOpenOffset, t('right away')),
            ],
            [
                t('Subscription deadline'),
                $series->subscriptionDeadlineOffset,
                $target->subscriptionDeadlineOffset,
                Offset::describe($series->subscriptionDeadlineOffset, t('at the start')),
                Offset::describe($target->subscriptionDeadlineOffset, t('at the start')),
            ],
        ];
        foreach (SeriesField::cases() as $field) {
            if (in_array($field, [SeriesField::Schedule, SeriesField::SubscriptionOpenDate, SeriesField::SubscriptionDeadline], true)) {
                continue;
            }
            $old = $field->expected($series, $series->startDate, $series->startDate);
            $new = $field->expected($target, $target->startDate, $target->startDate);
            $rows[] = [$field->label(), $old, $new, $field->format($old), $field->format($new)];
        }

        $changes = [];
        foreach ($rows as [$label, $old, $new, $oldText, $newText]) {
            if ($old !== $new) {
                $changes[] = ['label' => $label, 'old' => $oldText, 'new' => $newText];
            }
        }
        return $changes;
    }
}
