<?php

namespace App\Repository\Event;

use App\Entity\Event\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Question>
 */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    public function save(Question $question): void
    {
        $this->getEntityManager()->persist($question);
        $this->getEntityManager()->flush();
    }

    public function remove(Question $question): void
    {
        $this->getEntityManager()->remove($question);
        $this->getEntityManager()->flush();
    }
}
