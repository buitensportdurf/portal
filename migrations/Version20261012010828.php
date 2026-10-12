<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Recurrence\OccurrenceMatcher;
use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RRule\RRule;

/**
 * Rebuilds recurring events on RFC 5545 rules.
 *
 * Legacy rules (PHP relative date strings) are converted to RRULEs, every existing occurrence is
 * linked to the slot it was generated for, fields that were changed by hand are recorded as
 * overrides, gaps become exception dates, and the absolute subscription dates the old code copied
 * onto every occurrence are cleared.
 */
final class Version20261012010828 extends AbstractMigration
{
    private const array WEEKDAYS = [
        'monday' => 'MO', 'tuesday' => 'TU', 'wednesday' => 'WE', 'thursday' => 'TH',
        'friday' => 'FR', 'saturday' => 'SA', 'sunday' => 'SU',
    ];
    private const array ORDINALS = ['first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'last' => -1];
    private const array UNITS = ['day' => 'DAILY', 'week' => 'WEEKLY', 'month' => 'MONTHLY', 'year' => 'YEARLY'];

    /**
     * Template columns compared to find hand-made changes, keyed by SeriesField value.
     */
    private const array FIELD_COLUMNS = [
        'name' => 'name',
        'description' => 'description',
        'location' => 'location',
        'image' => 'image_id',
        'subscriberLimit' => 'subscriber_limit',
        'memberPrice' => 'member_price',
        'guestPrice' => 'guest_price',
        'guestsAllowed' => 'guests_allowed',
        'published' => 'published',
    ];

    public function getDescription(): string
    {
        return 'Recurring events on RFC 5545 rules, with occurrence slots, overrides and cancellation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY `FK_3BAE0AA7E54B259A`');
        $this->addSql('ALTER TABLE event ADD occurrence_start DATETIME DEFAULT NULL, ADD overridden_fields JSON DEFAULT NULL, ADD cancelled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7E54B259A FOREIGN KEY (recurring_event_id) REFERENCES recurring_event (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX event_occurrence ON event (recurring_event_id, occurrence_start)');
        $this->addSql('ALTER TABLE recurring_event ADD exception_dates JSON DEFAULT NULL, ADD subscription_open_offset INT DEFAULT NULL, ADD subscription_deadline_offset INT DEFAULT NULL, CHANGE recurrence_rule rrule VARCHAR(255) NOT NULL');

        // Reads run now, against the old schema; the updates are queued after the ALTERs above.
        foreach ($this->connection->fetchAllAssociative('SELECT * FROM recurring_event') as $series) {
            $this->convertSeries($series);
        }

        $this->addSql("UPDATE event SET overridden_fields = '[]' WHERE overridden_fields IS NULL");
        $this->addSql("UPDATE recurring_event SET exception_dates = '[]' WHERE exception_dates IS NULL");
        $this->addSql('ALTER TABLE event CHANGE overridden_fields overridden_fields JSON NOT NULL');
        $this->addSql('ALTER TABLE recurring_event CHANGE exception_dates exception_dates JSON NOT NULL, DROP subscription_deadline, DROP subscription_open_date');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Legacy recurrence rules cannot be restored; restore a backup instead.');
    }

    private function convertSeries(array $series): void
    {
        $id = (int) $series['id'];
        $rrule = self::convertRule($series['recurrence_rule']);
        $this->abortIf($rrule === null, sprintf(
            'Cannot convert recurrence rule "%s" of recurring event %d, convert it by hand first.',
            $series['recurrence_rule'], $id,
        ));

        $templateStart = new DateTimeImmutable($series['start_date']);
        $duration = $templateStart->diff(new DateTimeImmutable($series['end_date']));
        $events = $this->connection->fetchAllAssociative(
            'SELECT * FROM event WHERE recurring_event_id = ? ORDER BY start_date', [$id],
        );

        // Anchor the rule on the first occurrence, at the template's time of day, so DTSTART is a
        // real occurrence and an INTERVAL keeps the phase the existing events have.
        $anchor = $events ? new DateTimeImmutable($events[0]['start_date']) : $templateStart;
        $anchor = $anchor->setTime((int) $templateStart->format('H'), (int) $templateStart->format('i'));
        $rule = new RRule($rrule, $anchor);
        $dtstart = DateTimeImmutable::createFromInterface($rule->getOccurrencesAfter($anchor, true, 1)[0] ?? $anchor);
        $rule = new RRule($rrule, $dtstart);

        $exceptionDates = [];
        if ($events) {
            $last = new DateTimeImmutable(end($events)['start_date']);
            $slots = array_map(
                fn(\DateTimeInterface $date) => DateTimeImmutable::createFromInterface($date),
                $rule->getOccurrencesBetween($dtstart, $last->modify('+1 day')),
            );
            $items = [];
            foreach ($events as $index => $event) {
                $items[$index] = new DateTimeImmutable($event['start_date']);
            }
            $match = OccurrenceMatcher::match($items, $slots);

            $seriesTags = $this->tagIds('recurring_event_tag', 'recurring_event_id', $id);
            foreach ($events as $index => $event) {
                $slot = isset($match['pairs'][$index]) ? $slots[$match['pairs'][$index]] : null;
                $this->convertEvent($event, $series, $slot, $duration, $seriesTags);
            }
            // Gaps between linked occurrences were deleted by hand; slots past the last one never existed
            $lastMatched = $match['pairs'] ? max($match['pairs']) : -1;
            foreach ($match['unmatchedSlots'] as $slotIndex) {
                if ($slotIndex < $lastMatched) {
                    $exceptionDates[] = $slots[$slotIndex]->format('Y-m-d');
                }
            }
        }

        $this->addSql(
            'UPDATE recurring_event SET rrule = ?, start_date = ?, end_date = ?, exception_dates = ? WHERE id = ?',
            [
                $rrule,
                $dtstart->format('Y-m-d H:i:s'),
                $dtstart->add($duration)->format('Y-m-d H:i:s'),
                json_encode($exceptionDates),
                $id,
            ],
        );
    }

    private function convertEvent(
        array              $event,
        array              $series,
        ?DateTimeImmutable $slot,
        \DateInterval      $duration,
        array              $seriesTags,
    ): void
    {
        $start = new DateTimeImmutable($event['start_date']);
        $overridden = [];
        if ($slot !== null && ($start != $slot || new DateTimeImmutable($event['end_date']) != $start->add($duration))) {
            $overridden[] = 'schedule';
        }
        foreach (self::FIELD_COLUMNS as $field => $column) {
            if (self::normalise($column, $event[$column]) !== self::normalise($column, $series[$column])) {
                $overridden[] = $field;
            }
        }
        if ($this->tagIds('event_tag', 'event_id', (int) $event['id']) !== $seriesTags) {
            $overridden[] = 'tags';
        }

        // The old code copied the series' absolute subscription dates onto every occurrence; those
        // mean nothing for later occurrences (and closed subscriptions on all of them). Dates set
        // on the occurrence itself are kept as overrides.
        $sets = ['occurrence_start = ?', 'overridden_fields = ?'];
        foreach (['subscription_open_date' => 'subscriptionOpenDate', 'subscription_deadline' => 'subscriptionDeadline'] as $column => $field) {
            if ($event[$column] === null) {
                continue;
            }
            if ($event[$column] === $series[$column]) {
                $sets[] = "$column = NULL";
            } else {
                $overridden[] = $field;
            }
        }

        $this->addSql(
            sprintf('UPDATE event SET %s WHERE id = ?', implode(', ', $sets)),
            [$slot?->format('Y-m-d H:i:s'), json_encode(self::sortFields($overridden)), $event['id']],
        );
    }

    /**
     * A guest price of 0 and none mean the same (the old code turned one into the other).
     */
    private static function normalise(string $column, mixed $value): string
    {
        return $column === 'guest_price' && !$value ? '' : (string) $value;
    }

    /**
     * @return list<int>
     */
    private function tagIds(string $table, string $column, int $id): array
    {
        $ids = array_map(intval(...), $this->connection->fetchFirstColumn(
            "SELECT tag_id FROM $table WHERE $column = ?", [$id],
        ));
        sort($ids);

        return $ids;
    }

    /**
     * Same order as the SeriesField cases.
     *
     * @param list<string> $fields
     * @return list<string>
     */
    private static function sortFields(array $fields): array
    {
        $order = array_flip([
            'schedule', 'name', 'description', 'location', 'image', 'tags', 'subscriptionOpenDate',
            'subscriptionDeadline', 'subscriberLimit', 'memberPrice', 'guestPrice', 'guestsAllowed', 'published',
        ]);
        usort($fields, fn(string $a, string $b) => $order[$a] <=> $order[$b]);

        return $fields;
    }

    /**
     * Converts the legacy rule forms in use to an RRULE, or null for anything else.
     */
    public static function convertRule(string $legacy): ?string
    {
        $rule = strtolower(trim($legacy));
        $weekday = implode('|', array_keys(self::WEEKDAYS));
        $ordinal = implode('|', array_keys(self::ORDINALS));
        $unit = implode('|', array_keys(self::UNITS));

        // "1 week", "+2 days", "next week"
        if (preg_match("/^(?:\+?(\d+)|next)\s*($unit)s?$/", $rule, $m)) {
            return self::compose(self::UNITS[$m[2]], (int) ($m[1] ?: 1));
        }
        // "P1D", "P2W"
        if (preg_match('/^p(\d+)([dwmy])$/', $rule, $m)) {
            $units = ['d' => 'DAILY', 'w' => 'WEEKLY', 'm' => 'MONTHLY', 'y' => 'YEARLY'];

            return self::compose($units[$m[2]], (int) $m[1]);
        }
        // "first saturday of next month"
        if (preg_match("/^($ordinal) ($weekday) of next month$/", $rule, $m)) {
            return self::compose('MONTHLY', 1, self::ORDINALS[$m[1]] . self::WEEKDAYS[$m[2]]);
        }
        // "+2 months, first monday of this month"
        if (preg_match("/^\+?(\d+) months?,\s*($ordinal) ($weekday) of this month$/", $rule, $m)) {
            return self::compose('MONTHLY', (int) $m[1], self::ORDINALS[$m[2]] . self::WEEKDAYS[$m[3]]);
        }

        return null;
    }

    private static function compose(string $frequency, int $interval, ?string $byDay = null): string
    {
        return 'FREQ=' . $frequency
            . ($interval > 1 ? ';INTERVAL=' . $interval : '')
            . ($byDay !== null ? ';BYDAY=' . $byDay : '');
    }
}
