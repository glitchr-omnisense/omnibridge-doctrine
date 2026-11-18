<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Generators;

use Doctrine\ORM\Mapping as ORM;

/** An identifier from a custom generator, no #[GeneratedValue] said: doctrine/orm 3.8 reads the CUSTOM strategy. */
#[ORM\Entity]
class Token
{
    #[ORM\Id, ORM\Column(length: 32), ORM\CustomIdGenerator(class: TokenGenerator::class)]
    public ?string $id = null;

    /** A column with its character set and collation, in the column's options as Doctrine's attributes write them. */
    #[ORM\Column(length: 64, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    public string $code = '';
}
