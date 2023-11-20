<?php

namespace App\Repository\Event;

use App\Entity\Event\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 *
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array $criteria, array $orderBy = null)
 * @method Event[]    findAll()
 * @method Event[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @return array<Event>
     */
    public function findByTag(?string $tag): array
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('e')
            ->from($this->_entityName, 'e')
            ->addOrderBy('e.startDate', 'ASC');

        if ($tag) {
            $qb->join('e.tags', 't')
                ->where('t.name = :tag')
                ->setParameter('tag', $tag);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<Event>
     */
    public function findSubscribedByUser(User $user): array
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('e')
            ->from($this->_entityName, 'e')
            ->addOrderBy('e.startDate', 'ASC')
            ->join('e.eventSubscriptions', 's')
            ->join('s.createdUser', 'u')
            ->where('u.id = :user')
            ->setParameter('user', $user->getId()->toBinary());

        return $qb->getQuery()->getResult();
    }
}
