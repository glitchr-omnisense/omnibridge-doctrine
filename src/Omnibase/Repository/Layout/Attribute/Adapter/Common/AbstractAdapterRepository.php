<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\Common;

use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;

use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractAdapter|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method AbstractAdapter[]    findAll()
 * @method AbstractAdapter[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class AbstractAdapterRepository extends ServiceEntityRepository
{
}
