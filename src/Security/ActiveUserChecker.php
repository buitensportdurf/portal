<?php

namespace App\Security;

use App\Entity\User;
use App\Exception\AccountDisabledException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void {}

    /**
     * After the password check, so the message does not reveal which accounts exist.
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        /** @var User $user */
        if (!$user->isActive()) {
            throw new AccountDisabledException(
                'This account is not active yet. The committee activates new accounts, usually within a few days; you will get an email when that has happened.'
            );
        }
    }
}