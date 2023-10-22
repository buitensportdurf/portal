<?php

namespace App\Service;

use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

class RoleService
{
    private array $roles = [];

    public function __construct(
        private readonly RoleHierarchyInterface $hierarchy,
    )
    {
    }

    public function getRoleChoices(): array
    {
        array_walk($this->hierarchy, [$this, 'rolesRecursive']);
        return $this->roles;
    }

    private function rolesRecursive($roles, $key): void
    {
        if (is_string($key) && str_starts_with($key, 'ROLE_')) {
            $this->roles[strtolower(str_replace(['ROLE_', '_'], ['', ' '], $key))] = $key;
        }
        if (is_array($roles)) {
            array_walk($roles, [$this, 'rolesRecursive']);
        } else {
            $this->roles[strtolower(str_replace(['ROLE_', '_'], ['', ' '], $roles))] = $roles;
        }
    }
}