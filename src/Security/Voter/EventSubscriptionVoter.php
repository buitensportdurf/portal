<?php

namespace App\Security\Voter;

use App\Entity\Event\EventSubscription;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class EventSubscriptionVoter extends Voter
{
    public const SUBSCRIBE = 'subscribe';
    public const UNSUBSCRIBE = 'unsubscribe';

    public function __construct(
        private readonly Security $security,
    )
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        return in_array($attribute, [self::UNSUBSCRIBE, self::SUBSCRIBE])
            && $subject instanceof EventSubscription;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }
        if (!$subject instanceof EventSubscription) {
            return false;
        } else {
            $subscription = $subject;
        }

        switch ($attribute) {
            case self::SUBSCRIBE:
                if ($subscription->getEvent()->getSubscriptionDeadline() > new \DateTime()
                    || $this->security->isGranted('ROLE_EVENT_ADMIN')) {
                    return true;
                }
                break;
            case self::UNSUBSCRIBE:
                if ($subscription->getCreatedUser() === $user
                    || $this->security->isGranted('ROLE_EVENT_ADMIN')) {
                    return true;
                }
                break;
        }

        return false;
    }
}
