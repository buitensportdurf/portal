<?php

namespace App\Twig\Extension;

use App\Entity\Event\RecurringEvent;
use App\Recurrence\OccurrenceCalculator;
use App\Recurrence\Offset;
use DateTimeImmutable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class RecurrenceExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('recurrence_text', OccurrenceCalculator::describe(...)),
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
     * @return list<DateTimeImmutable>
     */
    public function nextOccurrences(RecurringEvent $series, int $limit = 5): array
    {
        return OccurrenceCalculator::next($series, new DateTimeImmutable(), $limit);
    }
}
