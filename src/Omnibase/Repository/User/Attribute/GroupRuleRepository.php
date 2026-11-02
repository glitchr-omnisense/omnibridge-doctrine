<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\User\Attribute;

use Base\Entity\User\Attribute\GroupRule;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method GroupRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupRule|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupRule[]    findAll()
 * @method GroupRule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRuleRepository extends ServiceEntityRepository
{
}
