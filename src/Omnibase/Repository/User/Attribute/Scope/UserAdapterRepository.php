<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\User\Attribute\Scope;

use Base\Entity\User\Attribute\Scope\UserAdapter;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method UserAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserAdapter|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserAdapter[]    findAll()
 * @method UserAdapter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserAdapterRepository extends ServiceEntityRepository
{
}
