<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Exception\AccountDisabledException;
use App\Security\ActiveUserChecker;
use PHPUnit\Framework\TestCase;

class ActiveUserCheckerTest extends TestCase
{
    private function createUser(bool $enabled = true): User
    {
        $user = new User();
        $user->username = 'testuser';
        $user->name = 'Test';
        $user->password = 'hashed';
        $user->enabled = $enabled;

        return $user;
    }

    public function testCheckPreAuthAllowsActiveUser(): void
    {
        $checker = new ActiveUserChecker();
        $user = $this->createUser(true);

        $checker->checkPreAuth($user);
        $this->addToAssertionCount(1); // no exception = pass
    }

    public function testCheckPreAuthThrowsForDisabledUser(): void
    {
        $checker = new ActiveUserChecker();
        $user = $this->createUser(false);

        $this->expectException(AccountDisabledException::class);
        $checker->checkPreAuth($user);
    }

    public function testCheckPostAuthDoesNothing(): void
    {
        $checker = new ActiveUserChecker();
        $user = $this->createUser();

        $checker->checkPostAuth($user);
        $this->addToAssertionCount(1);
    }
}
