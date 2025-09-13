<?php

namespace App\Repository\Event;

use App\Entity\Event\EventSubscription;
use App\Entity\Event\Question;
use App\Entity\Event\QuestionAnswer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestionAnswer>
 */
class QuestionAnswerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionAnswer::class);
    }

    public function save(QuestionAnswer $answer, bool $flush = true): void
    {
        $this->getEntityManager()->persist($answer);
        if($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByQuestionSubscription(
        Question          $question,
        EventSubscription $subscription,
    ): ?QuestionAnswer
    {
        return $this->findOneBy([
            'question' => $question,
            'subscription' => $subscription,
        ]);
    }
}
