<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\User;

use Base\Entity\User\Sanction;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method Sanction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sanction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sanction[]    findAll()
 * @method Sanction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method               findByUser($user)
 * @method               findByUserAndPenalty($user, $penalty)
 */
class SanctionRepository extends ServiceEntityRepository
{
}
