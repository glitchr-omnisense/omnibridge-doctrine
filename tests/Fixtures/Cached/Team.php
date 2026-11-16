<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Cached;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** A cached team (as a group): the inverse side of the cached many-to-many. */
#[ORM\Entity]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class Team
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    /** @var Collection<int, Member> */
    #[ORM\ManyToMany(targetEntity: Member::class, mappedBy: 'teams')]
    #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
    public Collection $members;

    public function __construct()
    {
        $this->members = new ArrayCollection();
    }
}
