<?php

namespace App\Recurrence;

/**
 * Formats the "minutes before start" offsets of a series.
 */
final class Offset
{
    public static function describe(?int $minutes, string $whenNull): string
    {
        if ($minutes === null) {
            return $whenNull;
        }
        if ($minutes === 0) {
            return 'at the start';
        }

        return match (true) {
            $minutes % 10080 === 0 => self::plural(intdiv($minutes, 10080), 'week'),
            $minutes % 1440 === 0 => self::plural(intdiv($minutes, 1440), 'day'),
            $minutes % 60 === 0 => self::plural(intdiv($minutes, 60), 'hour'),
            default => self::plural($minutes, 'minute'),
        } . ' before the start';
    }

    private static function plural(int $amount, string $unit): string
    {
        return $amount . ' ' . $unit . ($amount === 1 ? '' : 's');
    }
}
