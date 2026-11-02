<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\Hours;

use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;
use Base\Entity\Hours\SpecialDay;
use Base\Repository\Hours\SpecialDays;

/**
 * @method SpecialDay|null find($id, $lockMode = null, $lockVersion = null)
 * @method SpecialDay[]    findAll()
 */
class SpecialDayRepository extends ServiceEntityRepository implements SpecialDays
{
    /**
     * @param string|null $scope the place's own; null: the whole site's
     *
     * @return SpecialDay[] those not over by $from, soonest first
     */
    public function upcoming(\DateTimeInterface $from, int $limit = 50, ?string $scope = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.endsOn >= :from')->setParameter('from', $from->format('Y-m-d'))
            ->orderBy('s.startsOn', 'ASC')->setMaxResults($limit);
        null === $scope
            ? $qb->andWhere('s.scope IS NULL')
            : $qb->andWhere('s.scope = :scope')->setParameter('scope', $scope);

        return $qb->getQuery()->getResult();
    }
}
