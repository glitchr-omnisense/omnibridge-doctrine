<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\Index(name: 'idx_created', columns: ['createdAt'])]
abstract class Timestamps
{
    #[ORM\Column]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated', type: 'utc_datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $updatedAt = null;
}
