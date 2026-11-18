<?php

namespace Omnibridge\Doctrine\Type;

/**
 * omnibase's utc_datetime: a moment written and read in UTC, whatever zone PHP is in.
 *
 * Since 4.0 it is read as a \DateTimeImmutable, as utc_datetime_immutable is (DBAL 4.6 deprecates its mutable
 * DateTimeType, which it extended): the name stays, for the columns and applications that name it.
 */
class UtcDateTimeType extends UtcDateTimeImmutableType
{
    public const NAME = 'utc_datetime';

    public function getName(): string
    {
        return self::NAME;
    }
}
