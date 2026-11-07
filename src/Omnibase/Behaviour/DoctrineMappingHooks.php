<?php

namespace Omnibridge\Doctrine\Omnibase\Behaviour;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeInterface;
use Base\Database\Attribute\Cache;
use Base\Database\Attribute\OrderColumn;
use Base\Database\Attribute\Vault;
use Base\Database\Entity\EntityExtension;
use Base\Database\Entity\Extension\TranslationInterface;
use Base\Database\Type\SetType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\ORM\Mapping\ClassMetadata;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Omnibridge\Doctrine\Omnibase\Database\Walker\TranslatableWalker;

/**
 * The behaviours' mapping on Doctrine, read when Doctrine loads a class's metadata
 * (plan "omnibase agnostic", step F3): #[Cache]'s second-level cache regions,
 * #[Vault]'s unique constraints, #[OrderColumn]'s order field. They were the
 * attributes' own loadClassMetadata(), a hook typed on Doctrine's ClassMetadata;
 * the attributes keep only their neutral part.
 *
 * load() is what AttributeSubscriber (Doctrine's loadClassMetadata event) and
 * ClassMetadataManipulator call for each attribute of a class: the hook of the
 * attribute's class here, if any (a subclass of Cache, Vault or OrderColumn has its
 * parent's), then the attribute's own loadClassMetadata() - the deprecated
 * Doctrine-typed hook, which the other attributes (OrphanRemoval, Cascade,
 * DiscriminatorEntry, Hierarchify, Alias...) and an application's still implement,
 * and which does nothing on AbstractAttribute.
 */
final class DoctrineMappingHooks
{
    /** @var array<class-string<AbstractAttribute>, string> attribute => method of this class */
    public const HOOKS = [
        Cache::class => 'cache',
        Vault::class => 'vault',
        OrderColumn::class => 'orderColumn',
    ];

    public static function load(AttributeInterface $attribute, ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        foreach (self::HOOKS as $class => $method) {
            if ($attribute instanceof $class) {
                self::$method($attribute, $classMetadata, $target, $targetValue);
                break;
            }
        }

        $attribute->loadClassMetadata($classMetadata, $target, $targetValue);
    }

    /** #[Cache]: the class's region, and its associations' toward cached entities that its flags allow. */
    public static function cache(Cache $cache, ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        $region = $cache->getRegion($classMetadata);

        $usage = match ($cache->usage) {
            Cache::READ_ONLY => ClassMetadata::CACHE_USAGE_READ_ONLY,
            Cache::READ_WRITE => ClassMetadata::CACHE_USAGE_READ_WRITE,
            Cache::NONSTRICT_READ_WRITE => ClassMetadata::CACHE_USAGE_NONSTRICT_READ_WRITE,
            default => null,
        };

        switch ($target) {
            case EntityExtension::TARGET_CLASS:
                $classMetadata->cache = [
                    'usage' => $usage,
                    'region' => $region,
                ];

                foreach ($classMetadata->associationMappings as $property => $associationMapping) {
                    $isTargetEntityCached = !empty(AbstractAttribute::getAttributeReader()->getClassAttributes($associationMapping['targetEntity'], Cache::class));
                    if (!$isTargetEntityCached) {
                        continue;
                    }

                    self::cache($cache, $classMetadata, EntityExtension::TARGET_PROPERTY, $property);
                }

                break;

            case EntityExtension::TARGET_PROPERTY:
                if (($classMetadata->associationMappings[$targetValue]['type'] & $cache->associations) == 0) {
                    return;
                }

                $classMetadata->associationMappings[$targetValue]['cache'] = $classMetadata->associationMappings[$targetValue]['cache'] ?? [
                    'usage' => $usage,
                    'region' => $cache->getRegionProperty($classMetadata, $targetValue),
                ];

                break;
        }
    }

    /** #[Vault]: the unique constraint over its fields, and a translation's over its vault. */
    public static function vault(Vault $vault, ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        if (null === $classMetadata->reflClass) {
            return;
        } // Class has not yet been fully built, ignore this event

        if ($classMetadata->isMappedSuperclass) {
            return;
        }

        $namingStrategy = AbstractAttribute::getEntityManager()->getConfiguration()->getNamingStrategy();
        if ($vault->unique) {
            $name = $namingStrategy->classToTableName($classMetadata->name).'_unique';
            $classMetadata->table['uniqueConstraints'][$name]['columns'] = array_unique(array_merge(
                $classMetadata->table['uniqueConstraints'][$name]['columns'] ?? [],
                $vault->unique
            ));
        }

        if (is_instanceof($classMetadata->name, TranslationInterface::class)) {
            $name = $namingStrategy->classToTableName($classMetadata->rootEntityName).'_'.TranslatableWalker::SALT;
            if ($classMetadata->getName() == $classMetadata->rootEntityName) {
                $classMetadata->table['uniqueConstraints'][$name] ??= [];
                $classMetadata->table['uniqueConstraints'][$name]['columns'] = array_unique(array_merge(
                    $classMetadata->table['uniqueConstraints'][$name]['columns'] ?? [],
                    [$vault->vault]
                ));
            }
        }
    }

    /** #[OrderColumn]: a set or a to-many association's order field, JSON or string, mapped when it is not. */
    public static function orderColumn(OrderColumn $orderColumn, ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        if (!$orderColumn->supports($target, $targetValue, $classMetadata)) {
            return;
        }

        // Doctrine's manipulator, on the metadata being built: the reader's reads the entities' shapes on omnisense/omnibase
        // 5.x (step E3), which this class is not yet part of.
        $manipulator = AbstractAttribute::getClassMetadataManipulator();
        if (!$manipulator instanceof ClassMetadataManipulator) {
            $manipulator = new ClassMetadataManipulator();
        }

        // For associations/set, orderBy is mandatory and must be JSON or string
        $type = $manipulator->getTypeOfField($classMetadata, $targetValue);
        $doctrineType = $manipulator->getDoctrineType($type);
        $isSet = is_instanceof($doctrineType, SetType::class);
        $isToMany = $manipulator->isToManySide($classMetadata, $targetValue);

        if ($isSet || $isToMany) {
            if (!$orderColumn->orderBy) {
                throw new \Exception("The '".$classMetadata->name."::orderBy' field is mandatory for association '{$targetValue}'.");
            }

            $orderByType = $manipulator->getTypeOfField($classMetadata, $orderColumn->orderBy);
            if (!$orderByType) {
                $orderByType = 'json';
            }

            // Check if orderBy is mapped as JSON or string
            $doctrineOrderByType = $manipulator->getDoctrineType($orderByType);
            if (null === $doctrineOrderByType) {
                return;
            }

            $isJson = is_instanceof($doctrineOrderByType, JsonType::class) || 'json' === $doctrineOrderByType || JsonType::class === $doctrineOrderByType;
            $isString = is_instanceof($doctrineOrderByType, StringType::class) || 'string' === $doctrineOrderByType || StringType::class === $doctrineOrderByType;
            if (!$isJson && !$isString) {
                throw new \Exception("The '".$classMetadata->name."::orderBy' field '{$orderColumn->orderBy}' must be of type JSON or string for association '{$targetValue}'.");
            }

            // Map the orderBy column if not already mapped
            if (!$classMetadata->hasField($orderColumn->orderBy)) {
                $classMetadata->mapField([
                    'fieldName' => $orderColumn->orderBy,
                    'type' => $orderByType,
                    'nullable' => true,
                ]);
            }
        }
    }
}
