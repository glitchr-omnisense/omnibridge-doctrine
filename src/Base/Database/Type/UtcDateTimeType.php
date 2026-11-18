<?php

namespace Base\Database\Type;

/**
 * `utc_datetime`: a moment written in UTC and read back in UTC, whatever zone
 * PHP is in - since omnibridge/doctrine 4.0 as a \DateTimeImmutable, as
 * UtcDateTimeImmutableType reads it (it was its mutable twin; DBAL 4.6
 * deprecates the mutable DateTimeType it extended). The name stays.
 *
 * With base.database.use_custom (the default) omnibase's plain `datetime` is
 * UTC already; this type says so by name, and holds without that option. It
 * leaves the object it is given untouched.
 *
 * omnibridge/doctrine's UtcDateTimeType, under omnibase's name: written and
 * read as it writes and reads (in UTC; unreadable, the DBAL's own exception),
 * so a site mapped by omnibase and an application mapped by omnibridge/doctrine
 * read each other's rows.
 */
final class UtcDateTimeType extends \Omnibridge\Doctrine\Type\UtcDateTimeType
{
    public const NAME = 'utc_datetime';
}
