<?php

namespace Omnibridge\Doctrine\Tests;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Omnibridge\Doctrine\Type\DateTimeType;
use Omnibridge\Doctrine\Type\UtcDateTimeImmutableType;
use Omnibridge\Doctrine\Type\UtcDateTimeType;
use PHPUnit\Framework\TestCase;

/**
 * The date types: written and read in UTC, an unreadable value refused as DBAL 4
 * refuses it, and open to a framework's own subclass (omnibase's
 * Base\Database\Type\* extend them).
 */
final class TypesTest extends TestCase
{
    public function testADateIsWrittenAndReadInUtc(): void
    {
        $platform = new MySQLPlatform();
        $type = new DateTimeType();

        self::assertSame('2026-10-18 07:30:00', $type->convertToDatabaseValue(new \DateTime('2026-10-18 09:30:00', new \DateTimeZone('Europe/Paris')), $platform));
        $read = $type->convertToPHPValue('2026-10-18 07:30:00', $platform);
        self::assertSame('2026-10-18T07:30:00+00:00', $read->format(\DATE_ATOM));
    }

    public function testAnUnreadableValueIsAConversionExceptionAsInDbal4(): void
    {
        try {
            (new DateTimeType())->convertToPHPValue('not a date', new MySQLPlatform());
            self::fail('an unreadable value is refused');
        } catch (InvalidFormat $e) {
            self::assertInstanceOf(ConversionException::class, $e);
            self::assertStringContainsString('not a date', $e->getMessage());
        }
    }

    public function testTheUtcTypesMayBeExtended(): void
    {
        foreach ([UtcDateTimeType::class, UtcDateTimeImmutableType::class, DateTimeType::class] as $class) {
            self::assertFalse((new \ReflectionClass($class))->isFinal(), $class);
        }
    }
}
