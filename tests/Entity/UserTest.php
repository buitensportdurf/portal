<?php

namespace App\Tests\Entity;

use App\Entity\Group;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private function createUser(): User
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setName('Test User');
        $user->setPassword('hashed');

        return $user;
    }

    // --- Roles ---

    public function testDefaultRolesIncludeRoleUser(): void
    {
        $user = $this->createUser();
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testSetRolesAddsToExisting(): void
    {
        $user = $this->createUser();
        $user->setRoles(['ROLE_ADMIN']);

        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testRawRolesDoNotIncludeRoleUser(): void
    {
        $user = $this->createUser();
        self::assertNotContains('ROLE_USER', $user->getRawRoles());
    }

    public function testGroupRolesAreMerged(): void
    {
        $user = $this->createUser();
        $group = new Group();
        $group->setName('Event Managers');
        $group->setRoles(['ROLE_EVENT_EDIT']);
        $user->addGroup($group);

        self::assertContains('ROLE_EVENT_EDIT', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testRolesAreUnique(): void
    {
        $user = $this->createUser();
        $user->setRoles(['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']);

        $roles = $user->getRoles();
        self::assertSame(count($roles), count(array_unique($roles)));
    }

    public function testMultipleGroupRolesMerged(): void
    {
        $user = $this->createUser();

        $group1 = new Group();
        $group1->setName('Group 1');
        $group1->setRoles(['ROLE_EVENT_EDIT']);

        $group2 = new Group();
        $group2->setName('Group 2');
        $group2->setRoles(['ROLE_ADMIN_USER']);

        $user->addGroup($group1);
        $user->addGroup($group2);

        $roles = $user->getRoles();
        self::assertContains('ROLE_EVENT_EDIT', $roles);
        self::assertContains('ROLE_ADMIN_USER', $roles);
        self::assertContains('ROLE_USER', $roles);
    }

    // --- Guest Flag ---

    public function testNotGuestByDefault(): void
    {
        $user = $this->createUser();
        self::assertFalse($user->isGuest());
    }

    public function testCanBeSetAsGuest(): void
    {
        $user = $this->createUser();
        $user->setGuest(true);
        self::assertTrue($user->isGuest());
    }

    // --- Enabled / Active ---

    public function testEnabledByDefault(): void
    {
        $user = $this->createUser();
        self::assertTrue($user->isEnabled());
        self::assertTrue($user->isActive());
    }

    public function testCanBeDisabled(): void
    {
        $user = $this->createUser();
        $user->setEnabled(false);
        self::assertFalse($user->isEnabled());
        self::assertFalse($user->isActive());
    }

    // --- Identity ---

    public function testGetUserIdentifier(): void
    {
        $user = $this->createUser();
        self::assertSame('testuser', $user->getUserIdentifier());
    }

    public function testToString(): void
    {
        $user = $this->createUser();
        self::assertSame('Test User', (string) $user);
    }

    // --- Email ---

    public function testEmailNullByDefault(): void
    {
        $user = $this->createUser();
        self::assertNull($user->getEmail());
    }

    public function testSetEmail(): void
    {
        $user = $this->createUser();
        $user->setEmail('test@example.com');
        self::assertSame('test@example.com', $user->getEmail());
    }

    // --- API Key ---

    public function testApiKeyNullByDefault(): void
    {
        $user = $this->createUser();
        self::assertNull($user->getApiKey());
    }

    public function testSetApiKey(): void
    {
        $user = $this->createUser();
        $user->setApiKey('abc123');
        self::assertSame('abc123', $user->getApiKey());
    }

    // --- Groups ---

    public function testGroupsEmptyByDefault(): void
    {
        $user = $this->createUser();
        self::assertCount(0, $user->getGroups());
    }

    public function testAddGroupIdempotent(): void
    {
        $user = $this->createUser();
        $group = new Group();
        $group->setName('Test');
        $group->setRoles([]);

        $user->addGroup($group);
        $user->addGroup($group);

        self::assertCount(1, $user->getGroups());
    }

    public function testRemoveGroup(): void
    {
        $user = $this->createUser();
        $group = new Group();
        $group->setName('Test');
        $group->setRoles([]);

        $user->addGroup($group);
        $user->removeGroup($group);

        self::assertCount(0, $user->getGroups());
    }

    // --- EraseCredentials ---

    public function testEraseCredentials(): void
    {
        $user = $this->createUser();
        $user->eraseCredentials();
        $this->addToAssertionCount(1); // no-op, just ensure it doesn't throw
    }
}
