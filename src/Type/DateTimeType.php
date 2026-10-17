<?php

namespace Omnibridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;

/**
 * Doctrine's "datetime" as omnibase overrides it: written in UTC, read as UTC
 * (omnibase's Base\Database\Type\DateTimeTypeUTC).
 */
class DateTimeType extends \Doctrine\DBAL\Types\DateTimeType
{
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTime) {
            $value->setTimezone(Utc::zone());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTime
    {
        if (null === $value || $value instanceof \DateTime) {
            return $value;
        }
        $converted = \DateTime::createFromFormat($platform->getDateTimeFormatString(), (string) $value, Utc::zone());
        if (false === $converted) {
            // As DBAL 4's own types say it (a ConversionException): conversionFailedFormat() is DBAL 3's, gone.
            throw InvalidFormat::new((string) $value, static::class, $platform->getDateTimeFormatString());
        }

        return $converted;
    }
}
