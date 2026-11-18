<?php

namespace Omnibridge\Doctrine;

use Doctrine\DBAL\Types\TypeRegistry;
use Omnibridge\Doctrine\Type\DateTimeType;
use Omnibridge\Doctrine\Type\UtcDateTimeImmutableType;
use Omnibridge\Doctrine\Type\UtcDateTimeType;

/**
 * The DBAL types of omnibase's schema: datetime, datetimetz and their immutable twins written and read in UTC,
 * utc_datetime and utc_datetime_immutable - in a type provider given to the connection's configuration
 * (Doctrine\DBAL\Configuration::setTypeProvider()), never in DBAL's static registry (deprecated in DBAL 4.6, gone in 5).
 *
 *     $connection = DriverManager::getConnection($parameters, (new Configuration())->setTypeProvider(Types::provider()));
 *
 * EntityManagers::create() gives one to a connection configured without types of its own. Before any metadata is
 * read: a field's type is asked while it is.
 */
final class Types
{
    /** Over DBAL's types of these names. */
    private const OVERRIDES = [
        'datetime' => DateTimeType::class,
        'datetimetz' => DateTimeType::class,
        'datetime_immutable' => UtcDateTimeImmutableType::class,
        'datetimetz_immutable' => UtcDateTimeImmutableType::class,
    ];

    /** Beside them, where no type has the name yet (omnibase's own Base\Database\Type\Utc* are kept). */
    private const ADDED = [
        UtcDateTimeType::NAME => UtcDateTimeType::class,
        UtcDateTimeImmutableType::NAME => UtcDateTimeImmutableType::class,
    ];

    /**
     * The bridge's types in $registry (a registry of DBAL's built-in types when none is given), which is returned:
     * the type provider of a connection.
     */
    public static function provider(?TypeRegistry $registry = null): TypeRegistry
    {
        $registry ??= new TypeRegistry();
        foreach (self::OVERRIDES as $name => $className) {
            $registry->override($name, $className);
        }
        foreach (self::ADDED as $name => $className) {
            if (!$registry->has($name)) {
                $registry->register($name, $className);
            }
        }

        return $registry;
    }
}
