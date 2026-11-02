<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Common;

use Base\Entity\Layout\Attribute\Common\AbstractAction;

use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractAction|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractAction|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method AbstractAction[]    findAll()
 * @method AbstractAction[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class AbstractActionRepository extends ServiceEntityRepository
{
}
