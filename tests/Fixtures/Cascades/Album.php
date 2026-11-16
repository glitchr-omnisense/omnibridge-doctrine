<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Cascades;

use Base\Database\Attribute\Alias;
use Base\Database\Attribute\Cascade;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Database\Attribute\OrphanRemoval;
use Base\Entity\Thread;
use Base\Mapping as ORM;

/** omnibase's cascades on a Thread: the children's orphans removed through an #[Alias], the likes cascaded from the class. */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_album')]
#[DiscriminatorEntry(value: 'fixture_album')]
#[Cascade(column: 'likes', value: ['persist', 'remove'])]
class Album extends Thread
{
    #[Alias(column: 'children')]
    #[OrphanRemoval]
    protected $photos;
}
