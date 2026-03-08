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

    public function __construct(
        private readonly Security $security,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::SUBSCRIBE, self::UNSUBSCRIBE, self::PUBLISH, self::UNPUBLISH])
            && $subject instanceof Event;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var User $user */
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }
        if (!$subject instanceof Event) {
            return false;
        }
        $event = $subject;

        return match ($attribute) {
            self::PUBLISH => $this->canPublish($event),
            self::UNPUBLISH => $this->canUnpublish($event),
            self::SUBSCRIBE, self::UNSUBSCRIBE => $this->voteOnSubscription($attribute, $event, $user),
        };
    }

    private function canPublish(Event $event): bool
    {
        return $this->security->isGranted('ROLE_EVENT_EDIT') && !$event->isPublished();
    }

    private function canUnpublish(Event $event): bool
    {
        return $this->security->isGranted('ROLE_EVENT_EDIT')
            && $event->isPublished()
            && $event->getEventSubscriptions()->isEmpty();
    }

    private function voteOnSubscription(string $attribute, Event $event, User $user): bool
    {
        if ($this->security->isGranted('ROLE_EVENT_ADMIN')) {
            return true;
        }
        if (!$event->isPublished()) {
            return false;
        }
        if ($user->isGuest() && !$event->isGuestsAllowed()) {
            return false;
        }

        return match ($attribute) {
            self::SUBSCRIBE => $event->isPastSubscriptionOpenDate()
                && $event->isNotPastSubscriptionDeadline()
                && $event->isNotPastStartDate()
                && !$event->isSubscribed($user),
            self::UNSUBSCRIBE => $event->isSubscribed($user)
                && $event->isNotPastStartDate(),
            default => false,
        };
    }
}
