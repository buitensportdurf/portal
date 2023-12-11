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
     * @argument array<string> $hiddenTags
     * @return array<Event>
     */
    public function findByTag(?Tag $tag, array $hiddenTags = []): array
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('e')
           ->from($this->_entityName, 'e')
           ->leftJoin('e.tags', 't')
           ->addOrderBy('e.startDate', 'ASC')
        ;

        $andX = $qb->expr()->andX();
        $hiddenTags = array_diff($hiddenTags, [$tag]);
        foreach ($hiddenTags as $hiddenTag) {
            $key = 'hiddenTag' . $hiddenTag->getId();
            $andX->add(":$key NOT MEMBER OF e.tags");
            $qb->setParameter($key, $hiddenTag);
        }

        if ($tag) {
            $andX->add('t = :tag');
            $qb->setParameter('tag', $tag);
        } else {
            $qb->orWhere('e.tags IS EMPTY');
        }
        $qb->orWhere($andX);

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
            $this->_em->remove($image);
        }
        $this->_em->remove($event);
        $this->_em->flush();
    }
}
