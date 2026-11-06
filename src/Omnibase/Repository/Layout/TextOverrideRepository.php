<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\Layout;

use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;
use Base\Entity\Layout\TextOverride;

/**
 * @method TextOverride|null find($id, $lockMode = null, $lockVersion = null)
 * @method TextOverride[]    findAll()
 */
class TextOverrideRepository extends ServiceEntityRepository
{
    /** @return array<string, string> every rewritten text, by "domain|locale|key" */
    public function map(): array
    {
        $map = [];
        foreach ($this->findAll() as $override) {
            $map[$override->getDomain().'|'.$override->getLocale().'|'.$override->getKey()] = $override->getValue();
        }

        return $map;
    }
}
