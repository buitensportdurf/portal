<?php

namespace App\Entity\Event;

use App\Entity\Image;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class BaseEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $startDate;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $subscriptionDeadline;

    #[ORM\Column(length: 255)]
    private ?string $location = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    private ?Image $image = null;

    #[ORM\Column]
    private ?DateInterval $duration = null;

    #[ORM\Column(nullable: true)]
    private ?int $subscriberLimit = null;

    public function __construct()
    {
        $this->startDate = (new DateTimeImmutable())->setTime(18, 0);
        $this->subscriptionDeadline = (new DateTimeImmutable())->setTime(23, 59);
        $this->setTags(new ArrayCollection());
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function copyFrom(self $event): self
    {
        $this
            ->setName($event->getName())
            ->setDescription($event->getDescription())
            ->setSubscriptionDeadline($event->getSubscriptionDeadline())
            ->setSubscriberLimit($event->getSubscriberLimit())
            ->setLocation($event->getLocation())
            ->setImage($event->getImage())
            ->setDuration($event->getDuration())
            ->setTags($event->getTags())
            ->setStartDate($this->getStartDate()->setTime(
                $event->getStartDate()->format('H'),
                $event->getStartDate()->format('i')
            ))
        ;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public abstract function getTags(): Collection;

    public abstract function setTags(Collection $tags): static;

    public abstract function addTag(Tag $tag): static;

    public abstract function removeTag(Tag $tag): static;

    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSubscriptionDeadline(): ?DateTimeImmutable
    {
        return $this->subscriptionDeadline;
    }

    public function setSubscriptionDeadline(DateTimeImmutable $subscriptionDeadline): static
    {
        $this->subscriptionDeadline = $subscriptionDeadline;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getDuration(): ?DateInterval
    {
        return $this->duration;
    }

    public function setDuration(DateInterval $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getSubscriberLimit(): ?int
    {
        return $this->subscriberLimit;
    }

    public function setSubscriberLimit(?int $subscriberLimit): static
    {
        $this->subscriberLimit = $subscriberLimit;

        return $this;
    }
}