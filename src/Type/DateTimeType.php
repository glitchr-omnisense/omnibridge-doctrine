<?php

namespace Omnibridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;

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
            throw ConversionException::conversionFailedFormat((string) $value, 'datetime', $platform->getDateTimeFormatString());
        }

        return $converted;
    }
}
