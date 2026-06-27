<?php

namespace App\Tests\Service;

use App\Service\RoleService;
use PHPUnit\Framework\TestCase;

class RoleServiceTest extends TestCase
{
    public function testReturnsAllRolesAsLabelKeyedChoices(): void
    {
        $service = new RoleService([
            'ROLE_EVENT_ADMIN' => ['ROLE_EVENT_EDIT', 'ROLE_EVENT_RECURRING'],
            'ROLE_ADMIN' => ['ROLE_EVENT_ADMIN', 'ROLE_ADMIN_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
        ]);

        self::assertSame([
            'admin' => 'ROLE_ADMIN',
            'admin user' => 'ROLE_ADMIN_USER',
            'allowed to switch' => 'ROLE_ALLOWED_TO_SWITCH',
            'event admin' => 'ROLE_EVENT_ADMIN',
            'event edit' => 'ROLE_EVENT_EDIT',
            'event recurring' => 'ROLE_EVENT_RECURRING',
            'super admin' => 'ROLE_SUPER_ADMIN',
        ], $service->getRoleChoices());
    }

    public function testReturnsEmptyForEmptyHierarchy(): void
    {
        self::assertSame([], (new RoleService([]))->getRoleChoices());
    }

    public function testDeduplicatesRolesAppearingAsParentAndChild(): void
    {
        $service = new RoleService([
            'ROLE_PARENT' => ['ROLE_CHILD'],
            'ROLE_GRANDPARENT' => ['ROLE_PARENT'],
        ]);

        $choices = $service->getRoleChoices();

        self::assertCount(3, $choices);
        self::assertSame('ROLE_PARENT', $choices['parent']);
    }

    public function testIsIdempotentAcrossCalls(): void
    {
        $service = new RoleService(['ROLE_ADMIN' => ['ROLE_USER']]);

        self::assertSame($service->getRoleChoices(), $service->getRoleChoices());
    }
}
