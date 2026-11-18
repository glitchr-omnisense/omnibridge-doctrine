<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Generators;

use Doctrine\ORM\Mapping as ORM;

/** A custom generator kept aside by an explicit NONE strategy: the identifier stays assigned. */
#[ORM\Entity]
class Assigned
{
    #[ORM\Id, ORM\Column(length: 32), ORM\GeneratedValue(strategy: 'NONE'), ORM\CustomIdGenerator(class: TokenGenerator::class)]
    public ?string $id = null;
}
