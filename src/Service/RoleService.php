<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RoleService
{
    /**
     * @param array<string, list<string>> $hierarchy Role hierarchy from security.yaml.
     */
    public function __construct(
        #[Autowire(param: 'security.role_hierarchy.roles')]
        private readonly array $hierarchy,
    ) {}

    /**
     * @return array<string, string> Map of human-readable label to role identifier.
     */
    public function getRoleChoices(): array
    {
        $roles = array_unique(array_merge(
            array_keys($this->hierarchy),
            ...array_values($this->hierarchy),
        ));
        sort($roles);

        $choices = [];
        foreach ($roles as $role) {
            $choices[$this->toLabel($role)] = $role;
        }

        return $choices;
    }

    private function toLabel(string $role): string
    {
        return strtolower(str_replace(['ROLE_', '_'], ['', ' '], $role));
    }
}
