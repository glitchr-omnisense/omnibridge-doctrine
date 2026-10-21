<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string', length: 20)]
#[ORM\DiscriminatorMap(['notice' => Notice::class, 'alert' => Alert::class])]
class Notice
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(type: 'text')]
    protected string $message = '';
}
