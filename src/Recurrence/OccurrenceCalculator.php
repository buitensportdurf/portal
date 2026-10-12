<?php

namespace App\Recurrence;

use App\Entity\Event\RecurringEvent;
use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use RRule\RRule;
use Throwable;

/**
 * Expands a series' RRULE into occurrence slots.
 *
 * The rule is stored without DTSTART (the series start date is used) and UNTIL as a plain local
 * date (Ymd), which is what an admin means by "until"; both are resolved here.
 */
final class OccurrenceCalculator
{
    public const array FREQUENCIES = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];

    /**
     * @return list<DateTimeImmutable> slots in [$from, $to], minus the series' exception dates
     */
    public static function between(RecurringEvent $series, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $slots = [];
        foreach (self::build($series)->getOccurrencesBetween($from, $to) as $occurrence) {
            $slot = DateTimeImmutable::createFromInterface($occurrence);
            if (!$series->isExcluded($slot)) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * @return list<DateTimeImmutable> the next $limit slots from $from
     */
    public static function next(RecurringEvent $series, DateTimeInterface $from, int $limit): array
    {
        $slots = [];
        // Ask for enough to make up for the exception dates that get skipped
        $candidates = self::build($series)->getOccurrencesAfter($from, true, $limit + count($series->exceptionDates));
        foreach ($candidates as $occurrence) {
            $slot = DateTimeImmutable::createFromInterface($occurrence);
            if (!$series->isExcluded($slot)) {
                $slots[] = $slot;
            }
            if (count($slots) >= $limit) {
                break;
            }
        }

        return $slots;
    }

    /**
     * The rule in words, e.g. "every 2 weeks on Saturday" ($locale is 'en' or 'nl').
     */
    public static function describe(RecurringEvent $series, string $locale = 'en'): string
    {
        try {
            $dateFormatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, pattern: 'd MMM y');

            return self::build($series)->humanReadable([
                'locale' => $locale,
                'include_start' => false,
                'explicit_infinite' => false,
                'date_formatter' => fn(DateTimeInterface $date) => $dateFormatter->format($date),
            ]);
        } catch (Throwable) {
            return (string) $series->rrule;
        }
    }

    /**
     * @return string|null why the rule is unusable, or null when it is fine
     */
    public static function validate(RecurringEvent $series): ?string
    {
        try {
            $parts = self::parse((string) $series->rrule);
            if (!in_array($parts['FREQ'] ?? null, self::FREQUENCIES, true)) {
                return 'frequency must be daily, weekly, monthly or yearly';
            }
            self::build($series);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function parse(string $rrule): array
    {
        $parts = [];
        foreach (explode(';', strtoupper(trim(preg_replace('/^RRULE:/i', '', trim($rrule))))) as $part) {
            if ($part === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key] = $value;
        }

        return $parts;
    }

    /**
     * @param array<string, string> $parts
     */
    public static function compose(array $parts): string
    {
        $order = array_flip(['FREQ', 'INTERVAL', 'BYDAY', 'BYMONTHDAY', 'BYMONTH', 'BYSETPOS', 'COUNT', 'UNTIL']);
        uksort($parts, fn(string $a, string $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
        $parts = array_filter($parts, fn(?string $value) => $value !== null && $value !== '');

        return implode(';', array_map(fn(string $key, string $value) => "$key=$value", array_keys($parts), $parts));
    }

    private static function build(RecurringEvent $series): RRule
    {
        $parts = self::parse((string) $series->rrule);
        if (isset($parts['UNTIL'])) {
            $until = DateTimeImmutable::createFromFormat('!Ymd', substr($parts['UNTIL'], 0, 8));
            if ($until === false) {
                throw new \InvalidArgumentException('invalid end date');
            }
            $parts['UNTIL'] = $until->setTime(23, 59, 59);
        }
        $parts['DTSTART'] = $series->startDate;

        return new RRule($parts);
    }
}
