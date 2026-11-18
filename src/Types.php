<?php

namespace Omnibridge\Doctrine;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Omnibridge\Doctrine\Type\DateTimeType;
use Omnibridge\Doctrine\Type\UtcDateTimeImmutableType;
use Omnibridge\Doctrine\Type\UtcDateTimeType;

/**
 * The DBAL types of omnibase's schema, registered as omnibase registers them
 * (BaseBundle::boot(), BaseExtension::prependDoctrineTypes()): datetime,
 * datetimetz and their immutable twins written and read in UTC, utc_datetime and utc_datetime_immutable.
 * Before any metadata is read: a field's type is asked while it is.
 */
final class Types
{
    public static function register(): void
    {
        $registry = self::registry();
        $registry->override('datetime', DateTimeType::class);
        $registry->override('datetimetz', DateTimeType::class);
        $registry->override('datetime_immutable', UtcDateTimeImmutableType::class);
        $registry->override('datetimetz_immutable', UtcDateTimeImmutableType::class);
        foreach ([UtcDateTimeType::NAME => UtcDateTimeType::class, UtcDateTimeImmutableType::NAME => UtcDateTimeImmutableType::class] as $name => $class) {
            if (!$registry->has($name)) {
                $registry->register($name, $class);
            }
        }
    }

    /**
     * DBAL's registry, the one Type's static methods change (deprecated since DBAL 4.6): the type provider of a
     * connection configured without one (Configuration::getTypeProvider()), Type::getTypeRegistry() before 4.6.
     */
    private static function registry(): TypeRegistry
    {
        $configuration = new Configuration();
        // @phpstan-ignore function.alreadyNarrowedType (DBAL 4.2 to 4.5, which composer.json still allows, has no type provider)
        $registry = method_exists($configuration, 'getTypeProvider') ? $configuration->getTypeProvider() : Type::getTypeRegistry();
        \assert($registry instanceof TypeRegistry);

        return $registry;
    }
}
