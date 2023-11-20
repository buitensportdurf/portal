<?php

namespace App\Repository\Event;

use App\Entity\Event\RecurringEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecurringEvent>
 *
 * @method RecurringEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method RecurringEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method RecurringEvent[]    findAll()
 * @method RecurringEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class
RecurringEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecurringEvent::class);
    }

//    public function findByTag(?string $tag): array
//    {
//        $qb = $this->_em->createQueryBuilder();
//        $qb->select('e')
//            ->from($this->_entityName, 'e')
//            ->addOrderBy('e.startDate', 'ASC');
//
//        if ($tag) {
//            $qb->join('e.tags', 't')
//                ->where('t.name = :tag')
//                ->setParameter('tag', $tag);
//        }
//
//        return $qb->getQuery()->getResult();
//    }
//
//    public function findByUser(User $user): array
//    {
//        $qb = $this->_em->createQueryBuilder();
//        $qb->select('e')
//            ->from($this->_entityName, 'e')
//            ->addOrderBy('e.startDate', 'ASC')
//            ->join('e.eventSubscriptions', 's')
//            ->join('s.createdUser', 'u')
//            ->where('u.id = :user')
//            ->setParameter('user', $user->getId()->toBinary());
//
//        return $qb->getQuery()->getResult();
//    }
}
