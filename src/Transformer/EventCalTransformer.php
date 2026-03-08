<?php

namespace App\Transformer;

use App\Entity\Event\Event;
use Eluceo\iCal\Domain\Entity\Event as IcalEvent;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\MultiDay;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class EventCalTransformer
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function transform(Event $event): IcalEvent
    {
        if ($event->duration->d < 2) {
            $timeSpan = new TimeSpan(
                new DateTime($event->startDate, false),
                new DateTime($event->startDate->add($event->duration), false)
            );
        } else {
            $timeSpan = new MultiDay(
                new Date($event->startDate),
                new Date($event->startDate->add($event->duration))
            );
        }

        $icalEvent = new IcalEvent(new UniqueIdentifier(
            'portal.buitensportdurf.nl/event/' . $event->id
        ));
        $icalEvent
            ->setOccurrence($timeSpan)
            ->setUrl(new Uri($this->urlGenerator->generate(
                'event_event_show', ['id' => $event->id],
                UrlGeneratorInterface::ABSOLUTE_URL)
            ))
            ->setSummary($event->name)
            ->setDescription($event->description)
            ->setLocation(new Location($event->location))
        ;

        return $icalEvent;
    }
}
