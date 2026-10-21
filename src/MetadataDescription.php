<?php

namespace Omnibridge\Doctrine;

use Doctrine\DBAL\Schema\Column as SchemaColumn;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index as SchemaIndex;
use Doctrine\DBAL\Schema\Table as SchemaTable;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\Mapping\JoinColumnMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\ToOneOwningSideMapping;
use Doctrine\ORM\Tools\SchemaTool;
use Omnibridge\Schema\Column;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\Entity;
use Omnibridge\Schema\Field;
use Omnibridge\Schema\ForeignKey;
use Omnibridge\Schema\Index;
use Omnibridge\Schema\Inheritance;
use Omnibridge\Schema\JoinColumn;
use Omnibridge\Schema\JoinTable;
use Omnibridge\Schema\Relation;
use Omnibridge\Schema\Table;

/**
 * The description (Omnibridge\Schema\Description) of what an entity manager
 * maps: each class as its metadata hold it - table, fields, relations,
 * inheritance, with the names its naming strategy gave - and the tables its
 * SchemaTool would create, with their columns, keys, indexes and foreign keys.
 * What Doctrine resolved, written in the neutral form: compared with the
 * description built outside Doctrine, with the database's own schema, or
 * written to a file another persistence reads.
 *
 * $values gives the values of an enumeration or a set column of a type the
 * platform does not describe (an application's own types), null for the rest.
 */
final class MetadataDescription
{
    private const GENERATORS = [
        ClassMetadata::GENERATOR_TYPE_AUTO => 'auto',
        ClassMetadata::GENERATOR_TYPE_SEQUENCE => 'sequence',
        ClassMetadata::GENERATOR_TYPE_IDENTITY => 'identity',
        ClassMetadata::GENERATOR_TYPE_CUSTOM => 'custom',
    ];

    private const KINDS = [
        ClassMetadata::ONE_TO_ONE => Relation::ONE_TO_ONE,
        ClassMetadata::MANY_TO_ONE => Relation::MANY_TO_ONE,
        ClassMetadata::ONE_TO_MANY => Relation::ONE_TO_MANY,
        ClassMetadata::MANY_TO_MANY => Relation::MANY_TO_MANY,
    ];

    /**
     * @param list<string>|null                 $classNames the classes, those the mapping driver knows when null
     * @param (\Closure(Type): ?list<string>)|null $values
     */
    public static function of(EntityManagerInterface $entityManager, ?array $classNames = null, ?\Closure $values = null): Description
    {
        $classNames ??= $entityManager->getConfiguration()->getMetadataDriverImpl()?->getAllClassNames() ?? [];
        $classNames = array_values(array_unique($classNames));
        sort($classNames);

        $entities = [];
        $metadata = [];
        foreach ($classNames as $className) {
            $classMetadata = $entityManager->getClassMetadata($className);
            $entities[$classMetadata->getName()] = self::entity($classMetadata);
            if (!$classMetadata->isMappedSuperclass && !$classMetadata->isEmbeddedClass) {
                $metadata[] = $classMetadata;
            }
        }

        $tables = [];
        foreach ((new SchemaTool($entityManager))->getSchemaFromMetadata($metadata)->getTables() as $table) {
            $tables[$table->getName()] = self::table($table, $values);
        }
        ksort($tables);

        return new Description($entities, $tables);
    }

    public static function entity(ClassMetadata $metadata): Entity
    {
        $kind = $metadata->isMappedSuperclass ? Entity::MAPPED_SUPERCLASS : ($metadata->isEmbeddedClass ? Entity::EMBEDDABLE : Entity::ENTITY);
        $inheritance = null;
        if (!$metadata->isInheritanceTypeNone() && null !== $metadata->discriminatorColumn) {
            $column = $metadata->discriminatorColumn;
            $map = array_map('strval', $metadata->discriminatorMap);
            ksort($map);
            $inheritance = new Inheritance(
                $metadata->isInheritanceTypeJoined() ? Inheritance::JOINED : Inheritance::SINGLE_TABLE,
                new Column($column->name, $column->type, (bool) ($column->nullable ?? false), $column->length),
                $map,
                null === $metadata->discriminatorValue ? null : (string) $metadata->discriminatorValue,
            );
        }

        $generated = self::GENERATORS[$metadata->generatorType] ?? null;
        $fields = [];
        foreach ($metadata->fieldMappings as $property => $mapping) {
            $fields[$property] = self::field($mapping, $generated);
        }

        $relations = [];
        foreach ($metadata->associationMappings as $property => $mapping) {
            $relations[$property] = self::relation($mapping);
        }

        $subclasses = $metadata->subClasses;
        sort($subclasses);

        return new Entity(
            $metadata->getName(),
            Entity::ENTITY === $kind ? $metadata->getTableName() : null,
            $kind,
            $metadata->parentClasses[0] ?? null,
            Entity::ENTITY === $kind && $metadata->rootEntityName !== $metadata->getName() ? $metadata->rootEntityName : null,
            $inheritance,
            $metadata->identifier,
            $fields,
            $relations,
            $subclasses,
        );
    }

    private static function field(FieldMapping $mapping, ?string $generated): Field
    {
        return new Field(
            $mapping->fieldName,
            $mapping->columnName,
            $mapping->type,
            (bool) $mapping->nullable,
            $mapping->length,
            $mapping->precision,
            $mapping->scale,
            (bool) $mapping->unique,
            $mapping->options ?? [],
            (bool) $mapping->id,
            $mapping->id ? $generated : null,
            $mapping->enumType,
            $mapping->inherited,
            $mapping->declared,
        );
    }

    private static function relation(AssociationMapping $mapping): Relation
    {
        $joinColumns = $mapping instanceof ToOneOwningSideMapping ? array_map(self::joinColumn(...), $mapping->joinColumns) : [];
        $joinTable = null;
        if ($mapping instanceof ManyToManyOwningSideMapping) {
            $joinTable = new JoinTable(
                $mapping->joinTable->name,
                array_map(self::joinColumn(...), $mapping->joinTable->joinColumns),
                array_map(self::joinColumn(...), $mapping->joinTable->inverseJoinColumns),
            );
        }

        return new Relation(
            $mapping->fieldName,
            self::KINDS[$mapping->type()],
            $mapping->targetEntity,
            $mapping->isOwningSide(),
            $mapping->mappedBy ?? null,
            $mapping->inversedBy ?? null,
            $joinColumns,
            $joinTable,
            array_map(self::direction(...), $mapping->orderBy ?? []),
            $mapping->indexBy ?? null,
            (bool) $mapping->id,
            $mapping->inherited,
            $mapping->declared,
        );
    }

    /** ASC or DESC: the order as written, where Doctrine keeps its Order enumeration. */
    private static function direction(mixed $direction): string
    {
        return match (true) {
            $direction instanceof \BackedEnum => strtoupper((string) $direction->value),
            $direction instanceof \UnitEnum => ['Ascending' => 'ASC', 'Descending' => 'DESC'][$direction->name] ?? $direction->name,
            default => strtoupper((string) $direction),
        };
    }

    private static function joinColumn(JoinColumnMapping $mapping): JoinColumn
    {
        return new JoinColumn($mapping->name, $mapping->referencedColumnName, $mapping->nullable ?? true, (bool) $mapping->unique, $mapping->onDelete);
    }

    /** @param (\Closure(Type): ?list<string>)|null $values */
    public static function table(SchemaTable $table, ?\Closure $values = null): Table
    {
        $columns = [];
        foreach ($table->getColumns() as $column) {
            $columns[$column->getName()] = self::column($column, $values);
        }

        $indexes = [];
        foreach ($table->getIndexes() as $index) {
            if (!$index->isPrimary()) {
                $indexes[$index->getName()] = new Index($index->getName(), $index->getColumns(), $index->isUnique(), array_values(array_map('strtolower', $index->getFlags())));
            }
        }
        ksort($indexes);

        $foreignKeys = [];
        foreach ($table->getForeignKeys() as $foreignKey) {
            $foreignKeys[$foreignKey->getName()] = self::foreignKey($foreignKey);
        }
        ksort($foreignKeys);

        return new Table($table->getName(), $columns, $table->getPrimaryKey()?->getColumns() ?? [], $indexes, $foreignKeys);
    }

    /** @param (\Closure(Type): ?list<string>)|null $values */
    private static function column(SchemaColumn $column, ?\Closure $values): Column
    {
        $default = $column->getDefault();
        $precision = $column->getPrecision();
        $scale = $column->getScale();

        return new Column(
            $column->getName(),
            Type::lookupName($column->getType()),
            !$column->getNotnull(),
            $column->getLength(),
            $precision,
            null === $precision && 0 === $scale ? null : $scale, // DBAL's column has a scale of 0 where none was said

            $column->getFixed(),
            $column->getUnsigned(),
            $column->getAutoincrement(),
            \is_scalar($default) ? $default : null,
            null !== $values ? $values($column->getType()) : null,
        );
    }

    private static function foreignKey(ForeignKeyConstraint $foreignKey): ForeignKey
    {
        $onDelete = $foreignKey->onDelete();
        $onUpdate = $foreignKey->onUpdate();

        return new ForeignKey(
            $foreignKey->getName(),
            $foreignKey->getLocalColumns(),
            $foreignKey->getForeignTableName(),
            $foreignKey->getForeignColumns(),
            null === $onDelete || 'NO ACTION' === strtoupper($onDelete) ? null : strtoupper($onDelete),
            null === $onUpdate || 'NO ACTION' === strtoupper($onUpdate) ? null : strtoupper($onUpdate),
        );
    }
}
