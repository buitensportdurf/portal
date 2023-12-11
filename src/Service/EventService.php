<?php

namespace App\Service;

use App\Entity\Event\RecurringEvent;
use App\Entity\Event\Tag;
use App\Repository\Event\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

class EventService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagRepository          $tagRepository,
    ) {}

    public function deleteFutureEvents(RecurringEvent $recurringEvent): int
    {
        $deletedEventCount = 0;
        foreach ($recurringEvent->getFutureEvents() as $event) {
            $this->em->remove($event);
            $recurringEvent->removeEvent($event);
            $deletedEventCount++;
        }
        return $deletedEventCount;
    }

    public function createNewEvents(RecurringEvent $recurringEvent): int
    {
        $newEventCount = 0;
        $nextYear = new \DateTimeImmutable('+1 year');
        $recurringTag = $this->tagRepository->find(Tag::ID_RECURRING);
        while ($event = $recurringEvent->createNextEvent() and $event->getStartDate() < $nextYear) {
            $event->addTag($recurringTag);
            $this->em->persist($event);
            $newEventCount++;
        }
        // We need to remove the last event, because it is and should not persist
        $recurringEvent->removeEvent($event);
        return $newEventCount;
    }
}