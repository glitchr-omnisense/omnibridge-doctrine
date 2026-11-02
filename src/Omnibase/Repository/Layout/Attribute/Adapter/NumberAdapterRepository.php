<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\NumberAdapter;

use Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;

/**
 * @method NumberAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method NumberAdapter|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method NumberAdapter[]    findAll()
 * @method NumberAdapter[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class NumberAdapterRepository extends AbstractAdapterRepository
{
}
