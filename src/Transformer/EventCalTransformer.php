<?php

namespace App\Transformer;

use App\Entity\Event\Event;
use Eluceo\iCal\Domain\Entity\Event as IcalEvent;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EventCalTransformer
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function transform(Event $event): IcalEvent
    {
        $icalEvent = new IcalEvent();
        $icalEvent
            ->setOccurrence(new TimeSpan(
                new DateTime($event->getStartDate(), true),
                new DateTime($event->getStartDate()->add($event->getDuration()), true)
            ))
            ->setUrl(new Uri($this->urlGenerator->generate(
                'event_event_show', ['id' => $event->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL)
            ))
            ->setSummary($event->getName())
            ->setDescription($event->getDescription() ?? '')
            ->setLocation(new Location($event->getLocation()));

        return $icalEvent;
    }
}