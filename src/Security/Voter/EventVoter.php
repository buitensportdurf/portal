<?php

namespace App\Security\Voter;

use App\Entity\Event\Event;
use App\Entity\User;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class EventVoter extends Voter
{
    public const SUBSCRIBE = 'subscribe';
    public const UNSUBSCRIBE = 'unsubscribe';
    public const PUBLISH = 'publish';
    public const UNPUBLISH = 'unpublish';
    /** Whether to show a Subscribe button: to whoever may subscribe, and to visitors who could after logging in */
    public const OFFER_SUBSCRIPTION = 'offer_subscription';

    public function __construct(
        private readonly Security $security,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::SUBSCRIBE, self::UNSUBSCRIBE, self::PUBLISH, self::UNPUBLISH, self::OFFER_SUBSCRIPTION])
            && $subject instanceof Event;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject instanceof Event) {
            return false;
        }
        $event = $subject;
        /** @var User $user */
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return $attribute === self::OFFER_SUBSCRIPTION && $event->isOpenForSubscription();
        }

        return match ($attribute) {
            self::PUBLISH => $this->canPublish($event),
            self::UNPUBLISH => $this->canUnpublish($event),
            self::SUBSCRIBE, self::UNSUBSCRIBE => $this->voteOnSubscription($attribute, $event, $user),
            self::OFFER_SUBSCRIPTION => $this->voteOnSubscription(self::SUBSCRIBE, $event, $user),
        };
    }

    private function canPublish(Event $event): bool
    {
        return $this->security->isGranted('ROLE_EVENT_EDIT') && !$event->published;
    }

    private function canUnpublish(Event $event): bool
    {
        return $this->security->isGranted('ROLE_EVENT_EDIT')
            && $event->published
            && $event->getEventSubscriptions()->isEmpty();
    }

    private function voteOnSubscription(string $attribute, Event $event, User $user): bool
    {
        if ($attribute === self::SUBSCRIBE && $event->isCancelled()) {
            return false;
        }
        if ($this->security->isGranted('ROLE_EVENT_ADMIN')) {
            return true;
        }
        if (!$event->published) {
            return false;
        }
        if ($user->guest && !$event->guestsAllowed) {
            return false;
        }

        return match ($attribute) {
            self::SUBSCRIBE => $event->isOpenForSubscription() && !$event->isSubscribed($user),
            self::UNSUBSCRIBE => $event->isSubscribed($user)
                && $event->isNotPastStartDate(),
            default => false,
        };
    }
}
