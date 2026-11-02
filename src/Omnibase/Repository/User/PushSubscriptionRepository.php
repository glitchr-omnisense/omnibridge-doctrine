<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\User;

use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;
use Base\Entity\User\PushSubscription;
use Base\Repository\User\PushSubscriptions;

/**
 * @method PushSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method PushSubscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method PushSubscription[]    findAll()
 * @method PushSubscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PushSubscriptionRepository extends ServiceEntityRepository implements PushSubscriptions
{
    /** Users who enabled push in at least one browser. */
    public function countDistinctUsers(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.user)')
            ->getQuery()->getSingleScalarResult();
    }
}
