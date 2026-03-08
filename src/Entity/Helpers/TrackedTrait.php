<?php

namespace App\Entity\Helpers;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait TrackedTrait
{
    #[ORM\Column]
    public ?DateTimeImmutable $createdDate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public ?User $createdUser = null;

    public function setCreatedDateNowNoSeconds(): void
    {
        $now = new DateTimeImmutable();
        $this->createdDate = $now->setTime((int) $now->format('H'), (int) $now->format('i'));
    }
}
