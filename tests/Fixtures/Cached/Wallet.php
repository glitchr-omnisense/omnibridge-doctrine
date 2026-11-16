<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Cached;

use Doctrine\ORM\Mapping as ORM;

/** An entity kept in the second-level cache (as chapaland's users): a balance. */
#[ORM\Entity]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class Wallet
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public int $balance = 0;
}
