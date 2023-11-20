<?php

namespace App\Entity\Event;

use App\Repository\Event\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class RecurringEvent extends BaseEvent
{
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'recurringEvents')]
    private Collection $tags;

    #[ORM\OneToMany(mappedBy: 'recurringEvent', targetEntity: Event::class)]
    private Collection $events;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->events = new ArrayCollection();
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
}