<?php

namespace App\Entity\Helpers;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

trait TrackedTrait
{
    #[ORM\Column]
    #[ORM\JoinColumn(nullable: false)]
    private ?DateTime $createdDate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdUser = null;

    public function getCreatedUser(): ?User
    {
        return $this->createdUser;
    }

    public function setCreatedUser(?User $createdUser): self
    {
        $this->createdUser = $createdUser;

        return $this;
    }

    public function getCreatedDate(): ?DateTime
    {
        return $this->createdDate;
    }

    public function setCreatedDate(DateTime $createdDate): self
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    public function setCreatedDateNowNoSeconds(): self
    {
        $this->setCreatedDate(DateTime::createFromFormat('Y-m-d H:i', date('Y-m-d H:i')));

        return $this;
    }
}