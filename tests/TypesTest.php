<?php

namespace Omnibridge\Doctrine\Tests;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Omnibridge\Doctrine\Type\DateTimeType;
use Omnibridge\Doctrine\Type\UtcDateTimeImmutableType;
use Omnibridge\Doctrine\Type\UtcDateTimeType;
use Omnibridge\Doctrine\Types;
use Doctrine\DBAL\Types\Type;
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

        $paris = new \DateTime('2026-10-18 09:30:00', new \DateTimeZone('Europe/Paris'));
        self::assertSame('2026-10-18 07:30:00', $type->convertToDatabaseValue($paris, $platform));
        self::assertSame('Europe/Paris', $paris->getTimezone()->getName(), 'the object written is left as it is');
        $read = $type->convertToPHPValue('2026-10-18 07:30:00', $platform);
        self::assertInstanceOf(\DateTimeImmutable::class, $read);
        self::assertSame('2026-10-18T07:30:00+00:00', $read->format(\DATE_ATOM));
    }

    /** 4.0: no type of the bridge is DBAL's mutable DateTimeType (deprecated in DBAL 4.6, gone in 5). */
    public function testTheDateTypesAreImmutable(): void
    {
        foreach ([DateTimeType::class, UtcDateTimeType::class, UtcDateTimeImmutableType::class] as $class) {
            self::assertTrue(is_subclass_of($class, \Doctrine\DBAL\Types\DateTimeImmutableType::class), $class);
            self::assertFalse(is_subclass_of($class, \Doctrine\DBAL\Types\DateTimeType::class), $class);
        }
    }

    /** One time: every date and time type registered writes and reads UTC, the immutable ones too. */
    public function testEveryDateTimeTypeRegisteredIsUtc(): void
    {
        Types::register();
        $platform = new MySQLPlatform();
        $paris = new \DateTimeImmutable('2026-10-18 09:30:00', new \DateTimeZone('Europe/Paris'));
        foreach (['datetime', 'datetimetz', 'datetime_immutable', 'datetimetz_immutable', 'utc_datetime', 'utc_datetime_immutable'] as $name) {
            $type = Type::getType($name);
            $value = str_contains($name, 'immutable') ? $paris : \DateTime::createFromImmutable($paris);
            self::assertSame('2026-10-18 07:30:00', $type->convertToDatabaseValue($value, $platform), $name);
            $converted = $type->convertToPHPValue('2026-10-18 07:30:00', $platform);
            self::assertInstanceOf(\DateTimeImmutable::class, $converted, $name);
            self::assertSame('2026-10-18T07:30:00+00:00', $converted->format(\DATE_ATOM), $name);
        }
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
