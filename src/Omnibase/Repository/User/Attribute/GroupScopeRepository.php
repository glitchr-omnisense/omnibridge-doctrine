<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\User\Attribute;

use Base\Entity\User\Attribute\GroupScope;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method GroupScope|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupScope|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupScope[]    findAll()
 * @method GroupScope[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupScopeRepository extends ServiceEntityRepository
{
}
