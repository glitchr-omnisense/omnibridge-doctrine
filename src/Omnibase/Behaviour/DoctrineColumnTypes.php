<?php

namespace Omnibridge\Doctrine\Omnibase\Behaviour;

use Base\Database\Backend\ColumnTypes;
use Base\Database\Type\SetType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Type;
use Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork;
use Omnibridge\Doctrine\Omnibase\EnumTypeAdapter;

/**
 * ColumnTypes on Doctrine (omnisense/omnibase's plan "omnibase agnostic", step F5c, item 3): a field holds
 * an array where its DBAL type is a JsonType (json, the passkeys' trust path) or a set enumeration - what
 * EntityHydrator asked ClassMetadataManipulator::getDoctrineType() before.
 */
final class DoctrineColumnTypes implements ColumnTypes
{
    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function isArray(string $type): bool
    {
        if ('' === $type) {
            return false;
        }
        // The connection's types (Type::hasType() and Type::getType() are deprecated since DBAL 4.6), DBAL's
        // registry before 4.6: the same unless the host gave the connection a type provider of its own.
        $configuration = $this->unitOfWork->entityManager()->getConnection()->getConfiguration();
        // @phpstan-ignore function.alreadyNarrowedType (DBAL 4.2 to 4.5, which composer.json still allows, has no type provider)
        $types = method_exists($configuration, 'getTypeProvider') ? $configuration->getTypeProvider() : Type::getTypeRegistry();
        if (!$types->has($type)) {
            return false;
        }

        $doctrineType = $types->get($type);
        if ($doctrineType instanceof EnumTypeAdapter) {
            return is_a($doctrineType->enumeration(), SetType::class, true);
        }

        return $doctrineType instanceof JsonType;
    }
}
