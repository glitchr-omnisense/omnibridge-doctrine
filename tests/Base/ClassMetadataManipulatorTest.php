<?php

namespace Omnibridge\Doctrine\Tests\Base;

use PHPUnit\Framework\TestCase;

/**
 * A manipulator made without an entity manager (the one #[OrderColumn]'s mapping hook makes while Doctrine builds the
 * metadata) reads the column types without DBAL's static registry (4.0): an enumeration from the names the connection's
 * type provider registered (Base\Database\Type\Enumerations), the core's types from a provider of its own.
 */
final class ClassMetadataManipulatorTest extends TestCase
{
    public function testWithoutAnEntityManagerTheTypesAreTheProvidersNotDbalsStaticRegistry(): void
    {
        if (!class_exists(\Base\Enum\UserRole::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        \Omnibridge\Doctrine\Omnibase\Types::provider([], ['user_role' => \Base\Enum\UserRole::class]);
        $manipulator = new \Base\Database\Mapping\ClassMetadataManipulator();

        self::assertInstanceOf(\Base\Enum\UserRole::class, $manipulator->getDoctrineType('user_role'), 'the enumeration itself, as before');
        self::assertInstanceOf(\Base\Database\Type\ArrayType::class, $manipulator->getDoctrineType('array'));
        self::assertInstanceOf(\Base\Database\Type\DateTimeTypeUTC::class, $manipulator->getDoctrineType('datetime'));
        self::assertFalse((new \Doctrine\DBAL\Configuration())->getTypeProvider()->has('user_role'), 'DBAL\'s static registry left alone');
    }
}
