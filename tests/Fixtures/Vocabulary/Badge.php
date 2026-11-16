<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Vocabulary;

use Base\Mapping as ORM;

/** An entity written in omnibase's vocabulary (Base\Mapping), as the core's, the bundles' and the sites' are. */
#[ORM\Entity]
class Badge
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public string $label = '';
}
