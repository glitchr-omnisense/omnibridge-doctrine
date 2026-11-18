<?php

namespace Omnibridge\Doctrine\Tests;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Omnibridge\Doctrine\Type\DateTimeType;
use Omnibridge\Doctrine\Type\UtcDateTimeImmutableType;
use Omnibridge\Doctrine\Type\UtcDateTimeType;
use Omnibridge\Doctrine\Types;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Types\TypeRegistry;
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

    /** One time: every date and time type of the provider writes and reads UTC, the immutable ones too. */
    public function testEveryDateTimeTypeOfTheProviderIsUtc(): void
    {
        $types = Types::provider();
        $platform = new MySQLPlatform();
        $paris = new \DateTimeImmutable('2026-10-18 09:30:00', new \DateTimeZone('Europe/Paris'));
        foreach (['datetime', 'datetimetz', 'datetime_immutable', 'datetimetz_immutable', 'utc_datetime', 'utc_datetime_immutable'] as $name) {
            $type = $types->get($name);
            foreach ([$paris, \DateTime::createFromImmutable($paris)] as $value) {
                self::assertSame('2026-10-18 07:30:00', $type->convertToDatabaseValue($value, $platform), $name);
            }
            $converted = $type->convertToPHPValue('2026-10-18 07:30:00', $platform);
            self::assertInstanceOf(\DateTimeImmutable::class, $converted, $name);
            self::assertSame('2026-10-18T07:30:00+00:00', $converted->format(\DATE_ATOM), $name);
        }
    }

    /** 4.0: the types go in a provider given to a connection, DBAL's static registry is left as DBAL has it. */
    public function testTheProviderLeavesDbalsStaticRegistryAlone(): void
    {
        $static = (new Configuration())->getTypeProvider();
        $types = Types::provider();

        self::assertNotSame($static, $types);
        self::assertInstanceOf(DateTimeType::class, $types->get('datetime'));
        self::assertNotInstanceOf(DateTimeType::class, $static->get('datetime'));
        self::assertFalse($static->has(UtcDateTimeImmutableType::NAME));
    }

    public function testTheTypesAreAddedToARegistryGivenAndAUtcTypeOfItsOwnIsKept(): void
    {
        $own = new class extends UtcDateTimeImmutableType {};
        $registry = new TypeRegistry([UtcDateTimeImmutableType::NAME => $own]);

        self::assertSame($registry, Types::provider($registry));
        self::assertInstanceOf(DateTimeType::class, $registry->get('datetimetz'));
        self::assertSame($own, $registry->get(UtcDateTimeImmutableType::NAME));
        self::assertInstanceOf(UtcDateTimeType::class, $registry->get(UtcDateTimeType::NAME));
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
