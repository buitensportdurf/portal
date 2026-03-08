<?php

namespace App\Tests\Entity;

use App\Entity\Group;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
    private function createGroup(string $name = 'Test Group', array $roles = []): Group
    {
        $group = new Group();
        $group->name = $name;
        $group->roles = $roles;

        return $group;
    }

    private function createUser(string $name = 'user'): User
    {
        $user = new User();
        $user->username = $name;
        $user->name = $name;
        $user->password = 'hashed';

        return $user;
    }

    public function testSetAndGetName(): void
    {
        $group = $this->createGroup('Admins');
        self::assertSame('Admins', $group->name);
    }

    public function testToString(): void
    {
        $group = $this->createGroup('Event Managers');
        self::assertSame('Event Managers', (string) $group);
    }

    public function testSetAndGetRoles(): void
    {
        $group = $this->createGroup('Admins', ['ROLE_ADMIN', 'ROLE_EVENT_EDIT']);
        self::assertSame(['ROLE_ADMIN', 'ROLE_EVENT_EDIT'], $group->roles);
    }

    public function testRolesEmptyByDefault(): void
    {
        $group = new Group();
        self::assertSame([], $group->roles);
    }

    public function testUsersEmptyByDefault(): void
    {
        $group = $this->createGroup();
        self::assertCount(0, $group->getUsers());
    }

    public function testAddUserBidirectional(): void
    {
        $group = $this->createGroup('Team', ['ROLE_EVENT_EDIT']);
        $user = $this->createUser();

        $group->addUser($user);

        self::assertCount(1, $group->getUsers());
        self::assertTrue($user->getGroups()->contains($group));
        self::assertContains('ROLE_EVENT_EDIT', $user->getRoles());
    }

    public function testRemoveUserBidirectional(): void
    {
        $group = $this->createGroup('Team', ['ROLE_EVENT_EDIT']);
        $user = $this->createUser();

        $group->addUser($user);
        $group->removeUser($user);

        self::assertCount(0, $group->getUsers());
        self::assertFalse($user->getGroups()->contains($group));
        self::assertNotContains('ROLE_EVENT_EDIT', $user->getRoles());
    }

    public function testAddUserIdempotent(): void
    {
        $group = $this->createGroup();
        $user = $this->createUser();

        $group->addUser($user);
        $group->addUser($user);

        self::assertCount(1, $group->getUsers());
    }
}
