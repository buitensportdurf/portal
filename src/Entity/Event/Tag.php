<?php

namespace App\Entity\Event;

use App\Repository\Event\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['name'], message: 'There is already a tag with this name')]
#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Please enter a name')]
    #[Assert\Regex(pattern: '/^[a-z0-9_]+$/', message: 'Only lowercase letters, numbers and underscores are allowed')]
    private ?string $name = null;

    #[ORM\ManyToMany(targetEntity: Event::class, mappedBy: 'tags')]
    private Collection $events;

    #[ORM\ManyToMany(targetEntity: RecurringEvent::class, mappedBy: 'tags')]
    private Collection $recurringEvents;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->recurringEvents = new ArrayCollection();
    }

    public function __toString(): string
    {
        return ucfirst(str_replace('_', ' ', $this->name));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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
            $event->addTag($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->events->removeElement($event)) {
            $event->removeTag($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, RecurringEvent>
     */
    public function getRecurringEvents(): Collection
    {
        return $this->recurringEvents;
    }

    public function addRecurringEvent(RecurringEvent $recurringEvent): static
    {
        if (!$this->recurringEvents->contains($recurringEvent)) {
            $this->recurringEvents->add($recurringEvent);
            $recurringEvent->addTag($this);
        }

        return $this;
    }

    public function removeRecurringEvent(RecurringEvent $recurringEvent): static
    {
        if ($this->recurringEvents->removeElement($recurringEvent)) {
            $recurringEvent->removeTag($this);
        }

        return $this;
    }
}
