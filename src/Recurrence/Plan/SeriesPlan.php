<?php

namespace App\Recurrence\Plan;

use App\Entity\Event\RecurringEvent;
use DateTimeImmutable;

/**
 * The full changelist of a series edit, creation, extension or removal. Built by SeriesPlanner
 * without touching anything, shown to the admin, then carried out by SeriesSynchronizer.
 */
final readonly class SeriesPlan
{
    /**
     * @param RecurringEvent          $series         the stored series
     * @param RecurringEvent          $target         the series state to apply (a draft, or $series itself)
     * @param DateTimeImmutable|null  $splitAt        when set, $target becomes a new series from this date and $series ends before it
     * @param list<array{label: string, old: string, new: string}> $seriesChanges
     * @param list<OccurrenceChange>  $occurrences
     * @param list<string>            $exceptionDates exception dates the series applied to occurrences will have
     */
    public function __construct(
        public RecurringEvent     $series,
        public RecurringEvent     $target,
        public ?DateTimeImmutable $splitAt,
        public array              $seriesChanges,
        public array              $occurrences,
        public array              $exceptionDates,
        public bool               $removesSeries = false,
    ) {}

    /**
     * @return list<OccurrenceChange>
     */
    public function ofType(ChangeType ...$types): array
    {
        return array_values(array_filter(
            $this->occurrences,
            fn(OccurrenceChange $change) => in_array($change->type, $types, true),
        ));
    }

    /**
     * @return list<OccurrenceChange> unchanged occurrences that keep customised fields the series changed
     */
    public function keptOverrides(): array
    {
        return array_values(array_filter(
            $this->occurrences,
            fn(OccurrenceChange $change) => $change->keptOverrides !== [],
        ));
    }

    /**
     * Updates grouped by identical field changes, so "11 events: name A → B" reads as one line.
     *
     * @return list<array{changes: list<FieldChange>, occurrences: list<OccurrenceChange>}>
     */
    public function groupedUpdates(): array
    {
        $groups = [];
        foreach ($this->ofType(ChangeType::Update) as $change) {
            // Subscription dates follow each event's own start, so group those by field only
            $signature = serialize(array_map(
                fn(FieldChange $fieldChange) => $fieldChange->field->isRelativeToStart()
                    ? [$fieldChange->field->value]
                    : [$fieldChange->field->value, $fieldChange->old, $fieldChange->new],
                $change->changes,
            ));
            $groups[$signature] ??= ['changes' => $change->changes, 'occurrences' => []];
            $groups[$signature]['occurrences'][] = $change;
        }

        return array_values($groups);
    }

    public function count(ChangeType $type): int
    {
        return count($this->ofType($type));
    }

    /**
     * @return list<OccurrenceChange>
     */
    public function notifications(): array
    {
        return array_values(array_filter(
            $this->occurrences,
            fn(OccurrenceChange $change) => $change->notifiesSubscribers(),
        ));
    }

    public function notifiedSubscriberCount(): int
    {
        $count = 0;
        foreach ($this->notifications() as $change) {
            $count += count($change->recipients());
        }

        return $count;
    }

    public function hasOccurrenceChanges(): bool
    {
        return $this->ofType(ChangeType::Create, ChangeType::Move, ChangeType::Update, ChangeType::Cancel, ChangeType::Delete) !== [];
    }

    public function isEmpty(): bool
    {
        return $this->seriesChanges === [] && !$this->hasOccurrenceChanges() && !$this->removesSeries;
    }
}
