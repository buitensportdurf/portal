<?php

namespace App\Entity\Event;

use App\Entity\Helpers\TrackedTrait;
use App\Repository\Event\EventSubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: EventSubscriptionRepository::class)]
class EventSubscription
{
    use TrackedTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'eventSubscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\Column]
    #[Assert\Positive]
    private ?int $amount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\OneToMany(targetEntity: QuestionAnswer::class, mappedBy: 'subscription', cascade: ['persist'], orphanRemoval: true)]
    public Collection $questionAnswers;

    public function __construct() {
        $this->questionAnswers = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->event) {
            if ($this->event->getSubscriberLimit() && $this->event->getAmountOfSubscriptions() > $this->event->getSubscriberLimit()) {
                $context
                    ->buildViolation(sprintf('Subscription exceeds subscriber limit for this event, limit is %d', $this->event->getSubscriberLimit()))
                    ->atPath('amount')
                    ->addViolation()
                ;
            }
            if ($this->event->isSubscribed($this->getCreatedUser()) && $this->event->getSubscription($this->getCreatedUser()) !== $this) {
                $context
                    ->buildViolation('You are already subscribed to this event')
                    ->addViolation()
                ;
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;
        // Required to update the owning side of the relationship for validation
        if ($event && !$event->getEventSubscriptions()->contains($this)) {
            $event->addEventSubscription($this);
        }

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
