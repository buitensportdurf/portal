<?php

namespace App\Entity\Event;

use App\Entity\Image;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
abstract class BaseEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    public ?DateTimeImmutable $startDate;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    public ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description {
        get => $this->description ?? '';
        set(?string $value) { $this->description = $value; }
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $subscriptionDeadline = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $subscriptionOpenDate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    public ?string $location = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    public ?Image $image = null;

    #[ORM\Column]
    #[Assert\NotNull]
    public ?DateInterval $duration = null;

    #[ORM\Column(nullable: true)]
    public ?int $subscriberLimit = null;

    #[ORM\Column]
    private int $memberPrice = 0;

    #[ORM\Column(nullable: true)]
    private ?int $guestPrice = null;

    #[ORM\Column]
    public bool $guestsAllowed = false;

    #[ORM\Column]
    public bool $published = true;

    public function __construct()
    {
        $this->startDate = new DateTimeImmutable('tomorrow 18:00');
        $this->setTags(new ArrayCollection());
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function copyFrom(self $event): void
    {
        $this->name = $event->name;
        $this->description = $event->description;
        $this->subscriptionDeadline = $event->subscriptionDeadline;
        $this->subscriberLimit = $event->subscriberLimit;
        $this->location = $event->location;
        $this->image = $event->image;
        $this->duration = $event->duration;
        $this->setTags($event->getTags());
        $this->startDate = $this->startDate->setTime(
            (int) $event->startDate->format('H'),
            (int) $event->startDate->format('i')
        );
        $this->setMemberPrice($event->getMemberPrice());
        $this->setGuestPrice($event->getGuestPrice());
        $this->guestsAllowed = $event->guestsAllowed;
        $this->published = $event->published;
    }

    public abstract function getTags(): Collection;

    public abstract function setTags(Collection $tags): static;

    public abstract function addTag(Tag $tag): static;

    public abstract function removeTag(Tag $tag): static;

    public function getMemberPrice(): float
    {
        return $this->memberPrice / 100;
    }

    public function setMemberPrice(float $memberPrice): static
    {
        $this->memberPrice = (int) ($memberPrice * 100);

        return $this;
    }

    public function getGuestPrice(): ?float
    {
        return $this->guestPrice ? $this->guestPrice / 100 : null;
    }

    public function setGuestPrice(?float $guestPrice): static
    {
        $this->guestPrice = $guestPrice !== null ? (int) ($guestPrice * 100) : null;

        return $this;
    }
}
