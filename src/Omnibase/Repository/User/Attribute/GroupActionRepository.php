<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\User\Attribute;

use Base\Entity\User\Attribute\GroupAction;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method GroupAction|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupAction|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupAction[]    findAll()
 * @method GroupAction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupActionRepository extends ServiceEntityRepository
{
}
