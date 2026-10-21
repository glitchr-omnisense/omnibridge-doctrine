<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Article extends Thread
{
    #[ORM\Column(length: 100, nullable: true)]
    protected ?string $title = null;

    #[ORM\ManyToOne(fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'SET NULL')]
    protected ?User $author = null;

    /** @var array<Article> */
    #[ORM\ManyToMany(targetEntity: Article::class)]
    protected array $connexes = [];

    /** @var array<Tag> a join table declared with its columns, nested attributes */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'article_keywords', joinColumns: [new ORM\JoinColumn(name: 'article', referencedColumnName: 'id', onDelete: 'CASCADE')], inverseJoinColumns: [new ORM\JoinColumn(name: 'keyword', referencedColumnName: 'id')])]
    protected array $keywords = [];
}
