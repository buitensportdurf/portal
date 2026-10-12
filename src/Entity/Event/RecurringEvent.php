<?php

namespace App\Entity\Event;

use App\Recurrence\OccurrenceCalculator;
use App\Repository\Event\RecurringEventRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A series of events: the template every occurrence copies, plus an RFC 5545 recurrence rule.
 *
 * startDate/endDate are the first occurrence (DTSTART) and so also define the time of day and the
 * duration of every occurrence. Subscription dates are offsets before each occurrence's start.
 */
#[ORM\Entity(repositoryClass: RecurringEventRepository::class)]
class RecurringEvent extends BaseEvent
{
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'recurringEvents')]
    private Collection $tags;

    #[ORM\OneToMany(mappedBy: 'recurringEvent', targetEntity: Event::class)]
    #[ORM\OrderBy(['startDate' => \SortDirection::Ascending])]
    private Collection $events;

    /**
     * RRULE without DTSTART, e.g. "FREQ=MONTHLY;BYDAY=1SA".
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    public ?string $rrule = null;

    /**
     * Dates (Y-m-d) on which the rule should not produce an occurrence, because an admin deleted or
     * detached it. Keeps generation from bringing them back.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $exceptionDates = [];

    /**
     * Minutes before each occurrence's start that subscriptions open, null for "immediately".
     */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    public ?int $subscriptionOpenOffset = null;

    /**
     * Minutes before each occurrence's start that subscriptions close, null for "at the start".
     */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    public ?int $subscriptionDeadlineOffset = null;

    public function __construct()
    {
        parent::__construct();
        $this->tags = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    /**
     * A detached copy used as the edit target, so the original survives until the changes are applied.
     */
    public function __clone()
    {
        parent::__clone();
        $this->tags = new ArrayCollection($this->tags->toArray());
        $this->events = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validateRule(ExecutionContextInterface $context): void
    {
        if ($this->rrule === null || $this->startDate === null) {
            return;
        }
        $error = OccurrenceCalculator::validate($this);
        if ($error !== null) {
            $context->buildViolation('Invalid recurrence rule: {{ error }}')
                    ->setParameter('{{ error }}', $error)
                    ->atPath('rrule')
                    ->addViolation()
            ;
        }
    }

    /**
     * Copies the template, the rule and the offsets of $other onto this series.
     */
    public function copyTemplateFrom(self $other): void
    {
        $this->name = $other->name;
        $this->description = $other->description;
        $this->location = $other->location;
        $this->image = $other->image;
        $this->setTags(new ArrayCollection($other->getTags()->toArray()));
        $this->startDate = $other->startDate;
        $this->endDate = $other->endDate;
        $this->subscriberLimit = $other->subscriberLimit;
        $this->setMemberPrice($other->getMemberPrice());
        $this->setGuestPrice($other->getGuestPrice());
        $this->guestsAllowed = $other->guestsAllowed;
        $this->published = $other->published;
        $this->rrule = $other->rrule;
        $this->subscriptionOpenOffset = $other->subscriptionOpenOffset;
        $this->subscriptionDeadlineOffset = $other->subscriptionDeadlineOffset;
    }

    public function endFor(DateTimeImmutable $start): DateTimeImmutable
    {
        $duration = $this->getDuration();

        return $duration ? $start->add($duration) : $start;
    }

    public function subscriptionOpenFor(DateTimeImmutable $start): ?DateTimeImmutable
    {
        return $this->subscriptionOpenOffset === null ? null : $start->modify("-$this->subscriptionOpenOffset minutes");
    }

    public function subscriptionDeadlineFor(DateTimeImmutable $start): ?DateTimeImmutable
    {
        return $this->subscriptionDeadlineOffset === null ? null : $start->modify("-$this->subscriptionDeadlineOffset minutes");
    }

    public function isExcluded(DateTimeImmutable $slot): bool
    {
        return in_array($slot->format('Y-m-d'), $this->exceptionDates, true);
    }

    public function exclude(DateTimeImmutable $slot): void
    {
        if (!$this->isExcluded($slot)) {
            $this->exceptionDates[] = $slot->format('Y-m-d');
            sort($this->exceptionDates);
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
            $event->recurringEvent = $this;
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->events->removeElement($event)) {
            if ($event->recurringEvent === $this) {
                $event->recurringEvent = null;
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getFutureEvents(?DateTimeImmutable $now = null): Collection
    {
        $now ??= new DateTimeImmutable();

        return $this->getEvents()->filter(fn(Event $event) => $event->startDate >= $now);
    }

    /**
     * @return Collection<int, Event>
     */
    public function getPastEvents(?DateTimeImmutable $now = null): Collection
    {
        $now ??= new DateTimeImmutable();

        return $this->getEvents()->filter(fn(Event $event) => $event->startDate < $now);
    }
}
