<?php

namespace App\Twig\Extension;

use App\Entity\Event\RecurringEvent;
use App\Recurrence\OccurrenceCalculator;
use App\Recurrence\Offset;
use DateTimeImmutable;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class RecurrenceExtension extends AbstractExtension
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('recurrence_text', $this->recurrenceText(...)),
            // Returns a translatable message: render it with |trans
            new TwigFilter('offset_text', Offset::describe(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('next_occurrences', $this->nextOccurrences(...)),
        ];
    }

    /**
     * The rule in words, in the current locale.
     */
    public function recurrenceText(RecurringEvent $series, bool $capitalize = false): string
    {
        $text = OccurrenceCalculator::describe($series, $this->translator->getLocale());

        return $capitalize ? mb_ucfirst($text) : $text;
    }

    /**
     * @return list<DateTimeImmutable>
     */
    public function nextOccurrences(RecurringEvent $series, int $limit = 5): array
    {
        return OccurrenceCalculator::next($series, new DateTimeImmutable(), $limit);
    }
}
