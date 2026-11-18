<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Generators;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;

final class TokenGenerator extends AbstractIdGenerator
{
    public function generateId(EntityManagerInterface $em, ?object $entity): string
    {
        return 'token-'.spl_object_id((object) []);
    }
}
