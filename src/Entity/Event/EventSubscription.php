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
    public private(set) ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'eventSubscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    public ?Event $event = null {
        set {
            $this->event = $value;
            if ($value !== null && !$value->getEventSubscriptions()->contains($this)) {
                $value->addEventSubscription($this);
            }
        }
    }

    #[ORM\Column]
    #[Assert\Positive]
    public ?int $amount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $note = null;

    #[ORM\OneToMany(targetEntity: QuestionAnswer::class, mappedBy: 'subscription', cascade: ['persist'], orphanRemoval: true)]
    public Collection $questionAnswers;

    public function __construct() {
        $this->questionAnswers = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->event) {
            if ($this->event->subscriberLimit && $this->event->getAmountOfSubscriptions() > $this->event->subscriberLimit) {
                $context
                    ->buildViolation(sprintf('Subscription exceeds subscriber limit for this event, limit is %d', $this->event->subscriberLimit))
                    ->atPath('amount')
                    ->addViolation()
                ;
            }
            if ($this->event->isSubscribed($this->createdUser) && $this->event->getSubscription($this->createdUser) !== $this) {
                $context
                    ->buildViolation('You are already subscribed to this event')
                    ->addViolation()
                ;
            }
        }
    }
}
