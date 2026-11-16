<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Cached;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** A cached member of cached teams (as a user of groups): the owning side of a cached many-to-many, persist only. */
#[ORM\Entity]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class Member
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    /** @var Collection<int, Team> */
    #[ORM\ManyToMany(targetEntity: Team::class, inversedBy: 'members', cascade: ['persist'])]
    #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
    public Collection $teams;

    public function __construct()
    {
        $this->teams = new ArrayCollection();
    }
}
