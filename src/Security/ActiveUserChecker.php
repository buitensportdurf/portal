<?php

namespace App\Security;

use App\Entity\User;
use App\Exception\AccountDisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        /** @var User $user */
        if (!$user->isActive()) {
            throw new AccountDisabledException(
                'This account is not active. Please contact the Media Committee of the Durf Board to activate your account.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void {}
}