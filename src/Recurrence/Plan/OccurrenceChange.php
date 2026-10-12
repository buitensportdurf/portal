<?php

namespace App\Recurrence\Plan;

use App\Entity\Event\Event;
use App\Recurrence\SeriesField;
use DateTimeImmutable;

/**
 * What happens to one occurrence: an existing event (or none, for a create) and its new slot.
 */
final readonly class OccurrenceChange
{
    /**
     * @param list<FieldChange> $changes      fields that get the series value
     * @param list<SeriesField> $keptOverrides customised fields the series changed but this event keeps
     */
    public function __construct(
        public ChangeType        $type,
        public ?Event            $event,
        public DateTimeImmutable $slot,
        public array             $changes = [],
        public array             $keptOverrides = [],
    ) {}

    public function subscriberCount(): int
    {
        return $this->event?->getAmountOfSubscriptions() ?? 0;
    }

    /**
     * Whether subscribers get an email about this change.
     */
    public function notifiesSubscribers(): bool
    {
        if ($this->event === null || $this->event->getEventSubscriptions()->isEmpty()) {
            return false;
        }
        if ($this->type === ChangeType::Cancel) {
            return true;
        }
        foreach ($this->changes as $change) {
            if ($change->field->concernsSubscribers()) {
                return true;
            }
        }

        return false;
    }

    public function change(SeriesField $field): ?FieldChange
    {
        foreach ($this->changes as $change) {
            if ($change->field === $field) {
                return $change;
            }
        }

        return null;
    }
}
