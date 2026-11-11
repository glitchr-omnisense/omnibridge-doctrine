<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Referential;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Owner
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;
}
