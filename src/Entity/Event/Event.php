<?php

namespace App\Entity\Event;

use App\Entity\User;
use App\Recurrence\SeriesField;
use App\Repository\Event\EventRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\UniqueConstraint(name: 'event_occurrence', columns: ['recurring_event_id', 'occurrence_start'])]
class Event extends BaseEvent
{
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: EventSubscription::class, orphanRemoval: true)]
    /** @var Collection<EventSubscription> $eventSubscriptions */
    private Collection $eventSubscriptions;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'events')]
    private Collection $tags;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?RecurringEvent $recurringEvent = null;

    /**
     * The slot the series rule generated this occurrence for. Stays put when the occurrence itself
     * is rescheduled, so the series can still recognise it.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $occurrenceStart = null;

    /**
     * Fields (SeriesField values) that were changed on this occurrence and so are left alone by
     * series edits.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $overriddenFields = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $cancelledAt = null;

    /**
     * When subscribers got their reminder, so nobody gets it twice.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $reminderSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $subscriptionDeadline = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $subscriptionOpenDate = null;

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

    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    public function isOverridden(SeriesField $field): bool
    {
        return in_array($field->value, $this->overriddenFields, true);
    }

    public function markOverridden(SeriesField ...$fields): void
    {
        $this->overriddenFields = SeriesField::values(
            ...array_map(SeriesField::from(...), $this->overriddenFields),
            ...$fields,
        );
    }

    /**
     * @return list<SeriesField>
     */
    public function getOverriddenFields(): array
    {
        return array_map(SeriesField::from(...), $this->overriddenFields);
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
    /**
     * Whether subscribing is possible right now for someone who may; used to invite logged-out
     * visitors, for whom the voter can't decide.
     */
    public function isOpenForSubscription(): bool
    {
        return $this->published
            && !$this->isCancelled()
            && $this->isPastSubscriptionOpenDate()
            && $this->isNotPastSubscriptionDeadline()
            && $this->isNotPastStartDate();
    }

    public function getSubscriptionUnavailableReason(?User $user): ?TranslatableMessage
    {
        if ($this->isCancelled()) {
            return t('This event has been cancelled.');
        }
        if (!$this->published) {
            return t('This event is not published yet.');
        }
        if ($user !== null && $user->guest && !$this->guestsAllowed) {
            return t('This event is for members only.');
        }
        if (!$this->isNotPastStartDate()) {
            return t('This event has already taken place.');
        }
        if (!$this->isNotPastSubscriptionDeadline()) {
            return t('The subscription deadline has passed.');
        }
        if ($user !== null && $this->isSubscribed($user)) {
            return t('You are already subscribed.');
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
