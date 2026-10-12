<?php

namespace App\Repository\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
use App\Entity\User;
use App\Recurrence\SeriesSynchronizer;
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
    public function findByTag(?Tag $tag, array $hiddenTags = [], bool $includeUnpublished = false): array
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->leftJoin('e.tags', 't')
            ->where('e.startDate >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->addOrderBy('e.startDate', 'ASC')
        ;

        if (!$includeUnpublished) {
            $qb->andWhere('e.published = true');
        }

        $tagAnd = $qb->expr()->andX();
        $totalTagOr = $qb->expr()->orX();

        if ($tag) {
            $tagAnd->add('t = :tag');
            $qb->setParameter('tag', $tag);
        } else {
            foreach ($hiddenTags as $hiddenTag) {
                $key = 'hiddenTag' . $hiddenTag->id;
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
        $qb = $this->createQueryBuilder('e');
        $qb
            ->addOrderBy('e.startDate', 'ASC')
            ->join('e.eventSubscriptions', 's')
            ->join('s.createdUser', 'u')
            ->where('u.id = :user')
            ->setParameter('user', $user->id->toBinary())
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * How far back calendar feeds go. Calendar apps drop events that leave a feed, so this keeps
     * recent history visible without the feed growing forever.
     */
    public const string CALENDAR_HISTORY = '-1 year';

    /**
     * Published events for a calendar feed: everything from CALENDAR_HISTORY on (events still
     * running included), optionally only those with a tag or those a user subscribed to.
     *
     * @return array<Event>
     */
    public function findForCalendar(?Tag $tag = null, ?User $user = null): array
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->where('e.published = true')
            ->andWhere('e.endDate >= :since')
            ->setParameter('since', new \DateTimeImmutable(self::CALENDAR_HISTORY))
            ->addOrderBy('e.startDate', 'ASC')
        ;
        if ($tag !== null) {
            $qb->andWhere(':tag MEMBER OF e.tags')->setParameter('tag', $tag);
        }
        if ($user !== null) {
            $qb
                ->join('e.eventSubscriptions', 's')
                ->join('s.createdUser', 'u')
                ->andWhere('u.id = :user')
                ->setParameter('user', $user->id->toBinary())
            ;
        }

        return $qb->getQuery()->getResult();
    }

    public function remove(Event $event): void
    {
        // Check if image needs to be deleted
        $image = $event->image;
        if ($image && $event->recurringEvent?->image !== $image
            && count($this->findBy(['image' => $image])) === 1) {
            $this->getEntityManager()->remove($image);
        }
        // Keep the series from generating this occurrence again
        SeriesSynchronizer::release($event);
        $this->getEntityManager()->remove($event);
        $this->getEntityManager()->flush();
    }

    public function findPast(?int $year = null, bool $includeUnpublished = false): array
    {
        $qb = $this->createQueryBuilder('e');
        $qb
            ->where('e.startDate < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->addOrderBy('e.startDate', 'DESC')
        ;

        if (!$includeUnpublished) {
            $qb->andWhere('e.published = true');
        }

        if ($year !== null) {
            $qb
                ->andWhere('e.startDate >= :yearStart')
                ->andWhere('e.startDate < :yearEnd')
                ->setParameter('yearStart', new \DateTimeImmutable("$year-01-01"))
                ->setParameter('yearEnd', new \DateTimeImmutable(($year + 1) . '-01-01'))
            ;
        }

        return $qb->getQuery()->getResult();
    }

    /** @return int[] */
    public function findPastYears(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->fetchFirstColumn(
            'SELECT DISTINCT YEAR(start_date) AS y FROM event WHERE start_date < NOW() ORDER BY y DESC'
        );

        return array_map(intval(...), $rows);
    }

}
