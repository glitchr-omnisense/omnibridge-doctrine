<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** A thread named by omnibase's rules alone: no table or column name said. */
#[ORM\Entity]
class Thread
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public string $slug = '';

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTime $publishedAt = null;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;
}
