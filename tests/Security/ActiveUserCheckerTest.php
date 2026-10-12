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

    public function testCheckPostAuthAllowsActiveUser(): void
    {
        $checker = new ActiveUserChecker();

        $checker->checkPostAuth($this->createUser(true));
        $this->addToAssertionCount(1); // no exception = pass
    }

    public function testCheckPostAuthThrowsForDisabledUser(): void
    {
        $checker = new ActiveUserChecker();

        $this->expectException(AccountDisabledException::class);
        $checker->checkPostAuth($this->createUser(false));
    }

    public function testCheckPreAuthDoesNothing(): void
    {
        // Checking before the password would tell anyone which disabled accounts exist
        $checker = new ActiveUserChecker();

        $checker->checkPreAuth($this->createUser(false));
        $this->addToAssertionCount(1);
    }
}
