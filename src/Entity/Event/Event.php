<?php

namespace App\Entity\Event;

use App\Entity\User;
use App\Repository\Event\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event extends BaseEvent
{
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: EventSubscription::class, orphanRemoval: true)]
    /** @var Collection<EventSubscription> $eventSubscriptions */
    private Collection $eventSubscriptions;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'events')]
    private Collection $tags;

    #[ORM\ManyToOne(inversedBy: 'events')]
    private ?RecurringEvent $recurringEvent = null;

    public function __construct()
    {
        $this->eventSubscriptions = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    /**
     * @return Collection<int, EventSubscription>
     */
    public function getEventSubscriptions(): Collection
    {
        return $this->eventSubscriptions;
    }

    public function addEventSubscription(EventSubscription $eventSubscription): static
    {
        if (!$this->eventSubscriptions->contains($eventSubscription)) {
            $this->eventSubscriptions->add($eventSubscription);
            $eventSubscription->setEvent($this);
        }

        return $this;
    }

    public function removeEventSubscription(EventSubscription $eventSubscription): static
    {
        if ($this->eventSubscriptions->removeElement($eventSubscription)) {
            // set the owning side to null (unless already changed)
            if ($eventSubscription->getEvent() === $this) {
                $eventSubscription->setEvent(null);
            }
        }

        return $this;
    }

    public function getAmountOfSubscriptions(): int
    {
        $amount = 0;
        foreach ($this->eventSubscriptions as $eventSubscription) {
            $amount += $eventSubscription->getAmount();
        }
        return $amount;
    }

    public function isSubscribed(User $user): bool
    {
        return $this->getSubscription($user) !== null;
    }

    public function getSubscription(?User $user): ?EventSubscription
    {
        if ($user === null) {
            return null;
        }
        foreach ($this->eventSubscriptions as $eventSubscription) {
            if ($eventSubscription->getCreatedUser() === $user) {
                return $eventSubscription;
            }
        }
        return null;
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

    public function getRecurringEvent(): ?RecurringEvent
    {
        return $this->recurringEvent;
    }

    public function setRecurringEvent(?RecurringEvent $recurringEvent): static
    {
        $this->recurringEvent = $recurringEvent;

        return $this;
    }
}
