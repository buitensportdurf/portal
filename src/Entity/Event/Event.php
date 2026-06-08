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
    public ?RecurringEvent $recurringEvent = null;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Question::class, orphanRemoval: true)]
    /** @var ?Collection<Question> $questions */
    public ?Collection $questions = null;

    public function __construct()
    {
        parent::__construct();
        $this->eventSubscriptions = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->questions = new ArrayCollection();
    }

    public function isPastSubscriptionOpenDate(?\DateTime $date = null): bool
    {
        if ($this->subscriptionOpenDate === null) {
            return true;
        }
        $date ??= new \DateTime();

        return $this->subscriptionOpenDate <= $date;
    }

    public function isNotPastSubscriptionDeadline(?\DateTime $date = null): bool
    {
        if ($this->subscriptionDeadline === null) {
            return true;
        }
        $date ??= new \DateTime();

        return $this->subscriptionDeadline > $date;
    }

    public function isNotPastStartDate(?\DateTime $date = null): bool
    {
        if ($this->startDate === null) {
            return true;
        }
        $date ??= new \DateTime();

        return $this->startDate > $date;
    }

    /**
     * Explains why a regular member cannot subscribe, or null when subscribing is possible.
     *
     * Mirrors the conditions in EventVoter::voteOnSubscription(). The "subscriptions open later"
     * case is intentionally left to the template, which shows the formatted open date.
     */
    public function getSubscriptionUnavailableReason(?User $user): ?string
    {
        if (!$this->published) {
            return 'This event is not published yet.';
        }
        if ($user !== null && $user->guest && !$this->guestsAllowed) {
            return 'This event is for members only.';
        }
        if (!$this->isNotPastStartDate()) {
            return 'This event has already taken place.';
        }
        if (!$this->isNotPastSubscriptionDeadline()) {
            return 'The subscription deadline has passed.';
        }
        if ($user !== null && $this->isSubscribed($user)) {
            return 'You are already subscribed.';
        }

        return null;
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
            $eventSubscription->event = $this;
        }

        return $this;
    }

    public function removeEventSubscription(EventSubscription $eventSubscription): static
    {
        if ($this->eventSubscriptions->removeElement($eventSubscription)) {
            if ($eventSubscription->event === $this) {
                $eventSubscription->event = null;
            }
        }

        return $this;
    }

    public function getAmountOfSubscriptions(): int
    {
        $amount = 0;
        foreach ($this->eventSubscriptions as $eventSubscription) {
            $amount += $eventSubscription->amount;
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
            if ($eventSubscription->createdUser === $user) {
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

    public function setTags(Collection $tags): static
    {
        $this->tags = $tags;

        return $this;
    }
}
