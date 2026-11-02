<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Common;

use Base\Entity\Layout\Attribute\Common\AbstractScope;

use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractScope|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractScope|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method AbstractScope[]    findAll()
 * @method AbstractScope[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class AbstractScopeRepository extends ServiceEntityRepository
{
}
