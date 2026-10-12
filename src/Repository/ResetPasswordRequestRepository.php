<?php

namespace App\Repository;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;

/**
 * @extends ServiceEntityRepository<ResetPasswordRequest>
 *
 * @method ResetPasswordRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method ResetPasswordRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method ResetPasswordRequest[]    findAll()
 * @method ResetPasswordRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ResetPasswordRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetPasswordRequest::class);
    }

    /**
     * Tokens are stored as a SHA-256 hash, so a database leak does not hand out reset links.
     */
    public function findResetPasswordRequest(string $token): ?ResetPasswordRequest
    {
        return $this->findOneBy(['token' => self::hash($token)]);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function getMostRecentNonExpiredRequestDate(User $user): ?\DateTimeInterface
    {
        // Normally there is only 1 max request per use, but written to be flexible
        $resetPasswordRequest = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user->id, UlidType::NAME)
            ->orderBy('t.requestedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneorNullResult()
        ;

        if (null !== $resetPasswordRequest && !$resetPasswordRequest->isExpired()) {
            return $resetPasswordRequest->requestedAt;
        }

        return null;
    }

    public function removeResetPasswordRequest(ResetPasswordRequest $resetPasswordRequest): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->setParameter('user', $resetPasswordRequest->user->id, UlidType::NAME)
            ->getQuery()
            ->execute()
        ;
    }

    public function removeExpiredResetPasswordRequests(): int
    {
        $time = new \DateTimeImmutable('-1 week');
        $query = $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt <= :time')
            ->setParameter('time', $time)
            ->getQuery()
        ;

        return $query->execute();
    }
}
