<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Versioned;

use Base\Database\Attribute\Versionable;
use Doctrine\ORM\Mapping as ORM;

/** An entity whose title is versioned (VersionableSubscriberTest). */
#[ORM\Entity]
class Note
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    #[Versionable]
    public string $title = '';

    public function getId(): ?int
    {
        return $this->id;
    }
}
