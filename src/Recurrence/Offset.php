<?php

namespace App\Recurrence;

use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

/**
 * Formats the "minutes before start" offsets of a series. The result is translatable, so
 * templates render it with |trans.
 */
final class Offset
{
    /**
     * @param string|TranslatableInterface $whenNull returned as-is when there is no offset
     */
    public static function describe(?int $minutes, string|TranslatableInterface $whenNull): string|TranslatableInterface
    {
        if ($minutes === null) {
            return $whenNull;
        }
        if ($minutes === 0) {
            return t('at the start');
        }

        return match (true) {
            $minutes % 10080 === 0 => t('{count, plural, one {# week} other {# weeks}} before the start', ['count' => intdiv($minutes, 10080)]),
            $minutes % 1440 === 0 => t('{count, plural, one {# day} other {# days}} before the start', ['count' => intdiv($minutes, 1440)]),
            $minutes % 60 === 0 => t('{count, plural, one {# hour} other {# hours}} before the start', ['count' => intdiv($minutes, 60)]),
            default => t('{count, plural, one {# minute} other {# minutes}} before the start', ['count' => $minutes]),
        };
    }
}
