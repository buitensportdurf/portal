<?php

namespace App\Entity\Event;

use App\Entity\User;
use App\Repository\Event\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $subscriptionDeadline = null;

    #[ORM\Column(length: 255)]
    private ?string $location = null;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: EventSubscription::class, orphanRemoval: true)]
    /** @var Collection<EventSubscription> $eventSubscriptions */
    private Collection $eventSubscriptions;

    public function __construct()
    {
        $this->eventSubscriptions = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSubscriptionDeadline(): ?\DateTimeInterface
    {
        return $this->subscriptionDeadline;
    }

    public function setSubscriptionDeadline(\DateTimeInterface $subscriptionDeadline): static
    {
        $this->subscriptionDeadline = $subscriptionDeadline;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

        return $this;
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

    public function getSubscription(User $user): ?EventSubscription
    {
        foreach ($this->eventSubscriptions as $eventSubscription) {
            if ($eventSubscription->getCreatedUser() === $user) {
                return $eventSubscription;
            }
        }
        return null;
    }
}
