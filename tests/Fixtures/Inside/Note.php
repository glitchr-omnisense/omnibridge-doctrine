<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Inside;

use Base\Entity\Thread;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Mapping as ORM;

/** A subclass of the core's Thread in a second folder of the entity manager's paths (a bundle's). */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_note')]
#[DiscriminatorEntry(value: 'fixture_note')]
class Note extends Thread
{
}
