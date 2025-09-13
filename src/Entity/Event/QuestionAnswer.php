<?php

namespace App\Entity\Event;

use App\Repository\Event\QuestionAnswerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionAnswerRepository::class)]
class QuestionAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 1023, nullable: true)]
    public ?string $answer = null;

    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false)]
    public ?Question $question = null;

    #[ORM\ManyToOne(inversedBy: 'questionAnswers')]
    #[ORM\JoinColumn(nullable: false)]
    public ?EventSubscription $subscription = null {
        set {
            $this->subscription = $value;
            if ($value !== null && !$value->questionAnswers->contains($this)) {
                $value->questionAnswers->add($this);
            }
        }
    }

    public function __toString(): string
    {
        return $this->answer ?? '-';
    }
}
