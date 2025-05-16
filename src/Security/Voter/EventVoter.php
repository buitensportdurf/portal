<?php

namespace App\Security\Voter;

use App\Entity\Event\Event;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class EventVoter extends Voter
{
    public const SUBSCRIBE = 'subscribe';
    public const UNSUBSCRIBE = 'unsubscribe';

    public function __construct(
        private readonly Security $security,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        // https://symfony.com/doc/current/security/voters.html
        return in_array($attribute, [self::UNSUBSCRIBE, self::SUBSCRIBE])
            && $subject instanceof Event;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }
        if (!$subject instanceof Event) {
            return false;
        } else {
            $event = $subject;
        }
        if ($this->security->isGranted('ROLE_EVENT_ADMIN')) {
            return true;
        }

        $now = new DateTimeImmutable();
        switch ($attribute) {
            case self::SUBSCRIBE:
                if ($event->isNotPastSubscriptionDeadline()
                    && $event->isNotPastStartDate()
                    && !$event->isSubscribed($user)
                ) {
                    return true;
                }
                break;
            case self::UNSUBSCRIBE:
                if ($event->isSubscribed($user)
                    && $event->isNotPastStartDate()) {
                    return true;
                }
                break;
        }

        return false;
    }
}
