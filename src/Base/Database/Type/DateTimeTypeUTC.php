<?php

namespace Base\Database\Type;

/**
 * Doctrine's `datetime` (and `datetimetz`) as omnibase overrides them with
 * base.database.use_custom: a moment written in UTC, read back as a
 * \DateTimeImmutable in UTC (omnibridge/doctrine 4.0; a \DateTime before, which
 * DBAL 4.6 deprecates). Any \DateTimeInterface is written; the object given is
 * left as it is.
 *
 * The conversion is omnibridge/doctrine's DateTimeType, the one another
 * framework reading the same database registers (Omnibridge\Doctrine\Types):
 * one place says how a moment is stored. This name stays the type omnibase
 * registers, for the applications and bundles that name it.
 */
class DateTimeTypeUTC extends \Omnibridge\Doctrine\Type\DateTimeType
{
}
