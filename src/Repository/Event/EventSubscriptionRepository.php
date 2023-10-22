<?php

namespace App\Repository\Event;

use App\Entity\Event\EventSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventSubscription>
 *
 * @method EventSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventSubscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventSubscription[]    findAll()
 * @method EventSubscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventSubscription::class);
    }

    public function save(EventSubscription $eventSubscription): void
    {
        $this->_em->persist($eventSubscription);
        $this->_em->flush();
    }

    public function delete(EventSubscription $subscription): void
    {
        $this->_em->remove($subscription);
        $this->_em->flush();
    }
}
