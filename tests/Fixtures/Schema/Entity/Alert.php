<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Alert extends Notice
{
    #[ORM\Column]
    protected int $level = 0;
}
