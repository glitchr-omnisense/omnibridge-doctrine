<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Schema;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\NamingStrategy;
use Omnibridge\Schema\CamelCaseNaming;

/** The entity managers of the Schema tests: SQLite in memory, the fixtures' folder, the family's naming on the fixtures' namespace. */
final class Harness
{
    public const PREFIX = 'Omnibridge\\Doctrine\\Tests\\Fixtures\\Schema\\';

    /** @return list<class-string> */
    public static function classes(): array
    {
        return [
            Entity\Alert::class,
            Entity\Article::class,
            Entity\Notice::class,
            Entity\Profile::class,
            Entity\Tag::class,
            Entity\Thread::class,
            Entity\Timestamps::class,
            Entity\User::class,
        ];
    }

    public static function naming(): CamelCaseNaming
    {
        return new CamelCaseNaming([self::PREFIX]);
    }

    /** @param (\Closure(MappingDriver): MappingDriver)|null $driver what wraps the attribute driver */
    public static function entityManager(?\Closure $driver = null): EntityManager
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        return EntityManagers::create($connection, [__DIR__.'/Entity'], true, configure: static function (Configuration $configuration) use ($driver): void {
            $configuration->setNamingStrategy(new NamingStrategy(self::naming()));
            if (null !== $driver) {
                $configuration->setMetadataDriverImpl($driver($configuration->getMetadataDriverImpl()));
            }
        });
    }

    /**
     * A class's metadata as comparable data: every property but the reflection, the mapping objects' properties
     * that say something (not null, not false), the maps in a fixed order.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(ClassMetadata $metadata): array
    {
        $data = get_object_vars($metadata);
        unset($data['reflClass'], $data['reflFields']);
        $data['idGenerator'] = isset($metadata->idGenerator) ? $metadata->idGenerator::class : null;
        $data = self::normalize($data);
        ksort($data['discriminatorMap']);
        sort($data['subClasses']);
        if (\is_array($data['propertyAccessors'] ?? null)) {
            ksort($data['propertyAccessors']);
        }
        ksort($data);

        return $data;
    }

    private static function normalize(mixed $value): mixed
    {
        if (null === $value || \is_scalar($value)) {
            return $value;
        }
        if (\is_array($value)) {
            return array_map(self::normalize(...), $value);
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }
        if ($value instanceof \ReflectionClass) {
            return $value->getName();
        }
        if ($value instanceof \ReflectionProperty) {
            return $value->class.'::'.$value->getName();
        }
        if ($value instanceof Type) {
            return $value::class;
        }
        if ($value instanceof \Closure) {
            return '@closure';
        }

        $properties = method_exists($value, 'toArray') ? $value->toArray() : get_object_vars($value);

        return ['@class' => $value::class] + self::normalize(array_filter($properties, static fn ($property) => null !== $property && false !== $property));
    }
}
