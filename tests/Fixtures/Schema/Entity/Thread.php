<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping as ORM;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\State;

#[ORM\Entity(repositoryClass: EntityRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'class', type: 'string')]
#[ORM\HasLifecycleCallbacks]
class Thread extends Timestamps
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(unique: true)]
    protected string $slug = '';

    #[ORM\Column]
    protected State $state = State::Draft;

    /** @var array<Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'threads', cascade: ['persist'])]
    #[ORM\OrderBy(['label' => 'ASC'])]
    protected array $tags = [];

    #[ORM\PrePersist, ORM\PreUpdate]
    public function touch(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
