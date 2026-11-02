<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\Extension;

use Base\Entity\Extension\Ordering;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method Ordering|null find($id, $lockMode = null, $lockVersion = null)
 * @method Ordering|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ordering[]    findAll()
 * @method Ordering[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderingRepository extends ServiceEntityRepository
{
}
