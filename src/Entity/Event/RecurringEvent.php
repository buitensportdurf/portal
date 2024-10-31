<?php

namespace App\Entity\Event;

use App\Repository\Event\RecurringEventRepository;
use App\Service\TimeIntervalService;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: RecurringEventRepository::class)]
class RecurringEvent extends BaseEvent
{
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'recurringEvents')]
    private Collection $tags;

    #[ORM\OneToMany(mappedBy: 'recurringEvent', targetEntity: Event::class)]
    private Collection $events;

    #[ORM\Column]
    #[Assert\Callback(callback: [self::class, 'validateRecurrenceRule'])]
    private ?string $recurrenceRule = null;

    public function __construct()
    {
        parent::__construct();
        $this->tags = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    public function getRecurringDate(int $index, ?DateTimeImmutable $date = null): \DateTimeInterface
    {
        $date ??= $this->getStartDate();

        try {
            return TimeIntervalService::addIntervalsNTimes(
                $date, $this->getRecurrenceIntervals(), $index
            );
        } catch (Exception) {
            return $date;
        }
    }

    public function createNextEvent(): Event
    {
        $previousEvent = $this->getEvents()->last();
        $previousDate = $previousEvent ? $previousEvent->getStartDate() : $this->getStartDate();

        $event = (new Event())
            ->copyFrom($this)
            ->setStartDate(TimeIntervalService::addIntervalsNTimes(
                $previousDate, $this->getRecurrenceIntervals()
            ))
        ;
        $this->addEvent($event);

        return $event;
    }

    public static function validateRecurrenceRule(
        mixed                     $value,
        ExecutionContextInterface $context,
        mixed                     $payload
    ): void
    {
        try {
            $context->getObject()->getRecurrenceIntervals();
        } catch (Exception) {
            $context->buildViolation('Invalid recurrence rule')
                    ->atPath('recurrenceRule')
                    ->addViolation()
            ;
        }
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function setTags(Collection $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setRecurringEvent($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->events->removeElement($event)) {
            // set the owning side to null (unless already changed)
            if ($event->getRecurringEvent() === $this) {
                $event->setRecurringEvent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getFutureEvents(): Collection
    {
        $now = new DateTimeImmutable();
        return $this->getEvents()->filter(fn(Event $event) => $event->getStartDate() > $now);
    }

    public function getPastEvents(): Collection
    {
        $now = new DateTimeImmutable();
        return $this->getEvents()->filter(fn(Event $event) => $event->getStartDate() < $now);
    }

    public function getRecurrenceRule(): ?string
    {
        return $this->recurrenceRule;
    }

    public function setRecurrenceRule(string $recurrenceRule): static
    {
        $this->recurrenceRule = $recurrenceRule;

        return $this;
    }

    /**
     * @return DateInterval[]
     */
    public function getRecurrenceIntervals(): array
    {
        return array_map(
            fn(string $string) => DateInterval::createFromDateString($string),
            explode(';', $this->getRecurrenceRule())
        );
    }
}