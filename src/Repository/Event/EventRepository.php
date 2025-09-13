<?php

namespace App\Repository\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
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
     * @param array<Tag> $hiddenTags
     * @return array<Event>
     */
    public function findByTag(?Tag $tag, array $hiddenTags = []): array
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->leftJoin('e.tags', 't')
            ->where('e.startDate >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->addOrderBy('e.startDate', 'ASC')
        ;

        $tagAnd = $qb->expr()->andX();
        $totalTagOr = $qb->expr()->orX();

        if ($tag) {
            $tagAnd->add('t = :tag');
            $qb->setParameter('tag', $tag);
        } else {
            foreach ($hiddenTags as $hiddenTag) {
                $key = 'hiddenTag' . $hiddenTag->getId();
                $tagAnd->add(":$key NOT MEMBER OF e.tags");
                $qb->setParameter($key, $hiddenTag);
            }
        }
        if ($tagAnd->count() > 0) {
            $totalTagOr->add($tagAnd);
        }
        if (!$tag && !empty($hiddenTags)) {
            $totalTagOr->add('e.tags IS EMPTY');
        }
        if ($totalTagOr->count() > 0) {
            $qb->andWhere($totalTagOr);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<Event>
     */
    public function findSubscribedByUser(User $user): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('e')
           ->from($this->_entityName, 'e')
           ->addOrderBy('e.startDate', 'ASC')
           ->join('e.eventSubscriptions', 's')
           ->join('s.createdUser', 'u')
           ->where('u.id = :user')
           ->setParameter('user', $user->getId()->toBinary())
        ;

        return $qb->getQuery()->getResult();
    }

    public function remove(Event $event): void
    {
        // Check if image needs to be deleted
        $image = $event->getImage();
        if ($image && $event->getRecurringEvent()?->getImage() !== $image
            && count($this->findBy(['image' => $image])) === 1) {
            $this->getEntityManager()->remove($image);
        }
        $this->getEntityManager()->remove($event);
        $this->getEntityManager()->flush();
    }

    public function findPast(): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('e')
           ->from($this->_entityName, 'e')
           ->where('e.startDate < :now')
           ->setParameter('now', new \DateTimeImmutable())
           ->addOrderBy('e.startDate', 'DESC')
        ;

        return $qb->getQuery()->getResult();
    }

}
