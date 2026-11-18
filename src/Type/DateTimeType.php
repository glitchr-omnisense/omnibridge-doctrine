<?php

namespace Omnibridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;

/**
 * Doctrine's "datetime" (and "datetimetz") as omnibase overrides them: a moment written in UTC, read back as an
 * immutable moment in UTC (omnibase's Base\Database\Type\DateTimeTypeUTC).
 *
 * Since 4.0 the base is DBAL's DateTimeImmutableType (its mutable DateTimeType is deprecated in DBAL 4.6, gone in 5):
 * a column of this type is read as a \DateTimeImmutable. Any \DateTimeInterface is written, a \DateTime too, and the
 * object given is left as it is (3.x moved the entity's own \DateTime to UTC as it wrote it). The column is the
 * platform's DATETIME, as before: no schema changes, only the PHP class of what is read.
 */
class DateTimeType extends DateTimeImmutableType
{
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            $value = \DateTimeImmutable::createFromInterface($value)->setTimezone(Utc::zone());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->setTimezone(Utc::zone());
        }
        $converted = \DateTimeImmutable::createFromFormat($platform->getDateTimeFormatString(), (string) $value, Utc::zone());
        if (false === $converted) {
            // As DBAL 4's own types say it (a ConversionException).
            throw InvalidFormat::new((string) $value, static::class, $platform->getDateTimeFormatString());
        }

        return $converted;
    }
}
