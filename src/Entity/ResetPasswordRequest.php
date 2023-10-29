<?php

namespace App\Entity;

use App\Repository\ResetPasswordRequestRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
class ResetPasswordRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    protected ?string $token = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    protected ?DateTime $requestedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    protected ?DateTime $expiresAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): object
    {
        return $this->user;
    }

    public function getRequestedAt(): \DateTimeInterface
    {
        return $this->requestedAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt->getTimestamp() <= time();
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setUser(?User $user): ResetPasswordRequest
    {
        $this->user = $user;
        return $this;
    }

    public function setToken(?string $token): ResetPasswordRequest
    {
        $this->token = $token;
        return $this;
    }

    public function setRequestedAt(?DateTime $requestedAt): ResetPasswordRequest
    {
        $this->requestedAt = $requestedAt;
        return $this;
    }

    public function setExpiresAt(?DateTime $expiresAt): ResetPasswordRequest
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }
}
