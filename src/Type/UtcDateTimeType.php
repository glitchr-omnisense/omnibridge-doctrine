<?php

namespace Omnibridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType;

/** omnibase's utc_datetime: a moment written and read in UTC, whatever zone PHP is in. */
class UtcDateTimeType extends DateTimeType
{
    public const NAME = 'utc_datetime';

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            $value = \DateTime::createFromInterface($value)->setTimezone(Utc::zone());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTime
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTime::createFromInterface($value)->setTimezone(Utc::zone());
        }

        return \DateTime::createFromFormat($platform->getDateTimeFormatString(), (string) $value, Utc::zone())
            ?: (date_create((string) $value, Utc::zone()) ?: parent::convertToPHPValue($value, $platform));
    }
}
