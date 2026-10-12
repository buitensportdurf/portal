<?php

namespace App\Service;

use DateTimeInterface;
use IntlDateFormatter;

/**
 * Dates with day and month names in the current language. Symfony's LocaleSwitcher sets the
 * PHP default locale per request (and per email), so this needs no locale passed around.
 */
final class LocalDate
{
    public const string DATE_TIME = 'EEE d MMM y, HH:mm';
    public const string DATE = 'EEE d MMM y';
    public const string SHORT_DATE = 'd MMM y';
    public const string DAY_MONTH = 'd MMM';
    public const string DAY_MONTH_TIME = 'EEE d MMM, HH:mm';
    public const string LONG_DATE_TIME = 'EEEE d MMMM y, HH:mm';
    public const string LONG_DAY_MONTH_TIME = 'EEEE d MMMM, HH:mm';

    public static function format(DateTimeInterface $date, string $pattern, ?string $locale = null): string
    {
        $formatter = new IntlDateFormatter(
            $locale ?? \Locale::getDefault(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $date->getTimezone(),
            pattern: $pattern,
        );

        return (string) $formatter->format($date);
    }
}
