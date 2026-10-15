<?php

namespace App\Service;

use App\Entity\Event\Event;
use App\Transformer\EventCalTransformer;
use DateInterval;
use DateTimeImmutable;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\TimeZone;
use Eluceo\iCal\Presentation\Component\Property;
use Eluceo\iCal\Presentation\Component\Property\Value\TextValue;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;

/**
 * Renders events as an iCalendar document, for the calendar feeds and email attachments.
 */
readonly class CalendarBuilder
{
    public const string TIMEZONE = 'Europe/Amsterdam';

    public function __construct(
        private EventCalTransformer $transformer,
    ) {}

    /**
     * @param array<Event> $events
     * @param bool $withAttendees only for a feed that nobody but its owner can open
     */
    public function build(array $events, string $name, bool $withAttendees = false): string
    {
        $calendar = new Calendar(array_map(fn(Event $event) => $this->transformer->transform($event, $withAttendees), $events));

        // Event times refer to this timezone, so its definition has to cover every event in it
        $first = new DateTimeImmutable();
        $last = $first;
        foreach ($events as $event) {
            $first = min($first, $event->startDate);
            $last = max($last, $event->endDate);
        }
        $calendar->addTimeZone(TimeZone::createFromPhpDateTimeZone(
            new \DateTimeZone(self::TIMEZONE),
            $first->sub(new DateInterval('P1D')),
            $last->add(new DateInterval('P1D')),
        ));
        $calendar
            ->setProductIdentifier('-//Buitensport Durf//Portal//EN')
            ->setPublishedTTL(new DateInterval('PT1H'))
        ;

        return (string) new CalendarFactory()->createCalendar($calendar)
            ->withProperty(new Property('X-WR-CALNAME', new TextValue($name)))
            ->withProperty(new Property('X-WR-TIMEZONE', new TextValue(self::TIMEZONE)))
        ;
    }
}
