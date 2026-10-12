<?php

namespace App\Twig\Extension;

use App\Service\LocalDate;
use DateTimeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateFormatExtension extends AbstractExtension
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('time_duration', $this->formatTimeDuration(...)),
            new TwigFilter('fdate_time', fn(DateTimeInterface $date) => LocalDate::format($date, LocalDate::DATE_TIME)),
            new TwigFilter('fdate', fn(DateTimeInterface $date) => LocalDate::format($date, LocalDate::DATE)),
            new TwigFilter('fdate_short', fn(DateTimeInterface $date) => LocalDate::format($date, LocalDate::SHORT_DATE)),
            new TwigFilter('fday_month', fn(DateTimeInterface $date) => LocalDate::format($date, LocalDate::DAY_MONTH)),
        ];
    }

    public function formatTimeDuration(\DateInterval $interval): string
    {
        // Written out per unit, so translation:extract finds each message
        $parts = [];
        if ($interval->d > 0) {
            $parts[] = $this->translator->trans('{count, plural, one {# day} other {# days}}', ['count' => $interval->d]);
        }
        if ($interval->h > 0) {
            $parts[] = $this->translator->trans('{count, plural, one {# hour} other {# hours}}', ['count' => $interval->h]);
        }
        if ($interval->i > 0) {
            $parts[] = $this->translator->trans('{count, plural, one {# minute} other {# minutes}}', ['count' => $interval->i]);
        }
        if ($interval->s > 0) {
            $parts[] = $this->translator->trans('{count, plural, one {# second} other {# seconds}}', ['count' => $interval->s]);
        }

        return implode(' ', $parts);
    }
}
