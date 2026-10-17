<?php

namespace Omnibridge\Doctrine;

use Doctrine\DBAL\Types\Type;
use Omnibridge\Doctrine\Type\DateTimeType;
use Omnibridge\Doctrine\Type\UtcDateTimeImmutableType;
use Omnibridge\Doctrine\Type\UtcDateTimeType;

/**
 * The DBAL types of omnibase's schema, registered as omnibase registers them
 * (BaseBundle::boot(), BaseExtension::prependDoctrineTypes()): datetime and
 * datetimetz written and read in UTC, utc_datetime and utc_datetime_immutable.
 * Before any metadata is read: a field's type is asked while it is.
 */
final class Types
{
    public static function register(): void
    {
        Type::overrideType('datetime', DateTimeType::class);
        Type::overrideType('datetimetz', DateTimeType::class);
        foreach ([UtcDateTimeType::NAME => UtcDateTimeType::class, UtcDateTimeImmutableType::NAME => UtcDateTimeImmutableType::class] as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }
    }
}
