<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Outside;

use Base\Entity\Thread;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Mapping as ORM;

/** An entity outside the entity manager's paths whose parent (the core's Thread) is inside them. */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_post')]
#[DiscriminatorEntry(value: 'fixture_post')]
class Post extends Thread
{
}
