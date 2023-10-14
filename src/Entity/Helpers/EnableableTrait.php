<?php

namespace App\Entity\Helpers;

use Doctrine\ORM\Mapping as ORM;

trait EnableableTrait
{
    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }
}