<?php

namespace App\Recurrence;

use App\Entity\Event\RecurringEvent;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A series' rule in words, worded in the locale it is rendered in (via |trans in templates).
 */
final readonly class RecurrenceDescription implements TranslatableInterface, \Stringable
{
    public function __construct(
        private RecurringEvent $series,
    ) {}

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return OccurrenceCalculator::describe($this->series, $locale ?? $translator->getLocale());
    }

    public function __toString(): string
    {
        return OccurrenceCalculator::describe($this->series);
    }
}
