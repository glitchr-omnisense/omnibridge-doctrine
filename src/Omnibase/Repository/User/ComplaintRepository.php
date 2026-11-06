<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\User;

use Base\Entity\User\Complaint;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/**
 * @method Complaint|null find($id, $lockMode = null, $lockVersion = null)
 * @method Complaint|null findOneBy(array $criteria, array $orderBy = null)
 * @method Complaint[]    findAll()
 * @method Complaint[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComplaintRepository extends ServiceEntityRepository
{
    /**
     * The newest complaints, of one status (and one source) or all.
     *
     * @return Complaint[]
     */
    public function newest(?string $status = null, ?string $source = null, int $limit = 200): array
    {
        $criteria = array_filter(["status" => $status, "source" => $source], static fn ($value) => null !== $value);

        return $this->findBy($criteria, ["createdAt" => "DESC"], $limit);
    }
}
