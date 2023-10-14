<?php

namespace App\Entity\Helpers;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

trait TrackedTrait
{
    #[ORM\Column]
    #[ORM\JoinColumn(nullable: false)]
    private ?DateTime $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getChartTime(): int
    {
        return $this->getCreatedAt()->getTimestamp() * 1000;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCode(): string
    {
        return date_format($this->createdAt, "ymd");
    }

    public function setCreatedAtTodayNoSeconds(): self
    {
        $this->setCreatedAt(DateTime::createFromFormat('Y-m-d H:i', date('Y-m-d H:i')));

        return $this;
    }
}