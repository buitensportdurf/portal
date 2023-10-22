<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        /** @var User $user */
        if (!$user->isActive()) {
            throw new \Exception('This account is not active.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}