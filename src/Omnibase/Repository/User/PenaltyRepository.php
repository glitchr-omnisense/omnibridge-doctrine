<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\User;

use Base\Entity\User\Penalty;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method Penalty|null find($id, $lockMode = null, $lockVersion = null)
 * @method Penalty|null findOneBy(array $criteria, array $orderBy = null)
 * @method Penalty[]    findAll()
 * @method Penalty[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PenaltyRepository extends ServiceEntityRepository
{
}
