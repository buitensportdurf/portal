<?php

namespace App\Entity\Event;

use App\Repository\Event\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 1023)]
    public ?string $question = null;

    #[ORM\Column]
    public ?bool $required = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    public ?Event $event = null;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: QuestionAnswer::class, orphanRemoval: true)]
    public Collection $answers;

    #[ORM\Column(length: 255, nullable: false)]
    public ?string $type = null;

    public function __construct() {
        $this->answers = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->question ?? '';
    }
}
