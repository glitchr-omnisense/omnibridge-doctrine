<?php

namespace Omnibridge\Doctrine;

use Doctrine\DBAL\Schema\Column as SchemaColumn;
use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index as SchemaIndex;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table as SchemaTable;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
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
        $length = $entityManager->getConnection()->getDatabasePlatform()->getMaxIdentifierLength();
        foreach (self::schemaTool($entityManager)->getSchemaFromMetadata($metadata)->getTables() as $table) {
            $tables[$table->getName()] = self::describe($table, $values, $length);
        }
        ksort($tables);

        return new Description($entities, $tables);
    }

    /**
     * The SchemaTool of $entityManager on the schema editor API where DBAL has it (4.5 and later; doctrine/orm 3.8
     * deprecates the mutable schema objects there): the entity manager's own configuration with that API, a copy, so
     * the host's SchemaTools are left as configured.
     */
    private static function schemaTool(EntityManagerInterface $entityManager): SchemaTool
    {
        $configuration = $entityManager->getConfiguration();
        // @phpstan-ignore function.alreadyNarrowedType (DBAL 4.2 to 4.4, which composer.json still allows, has no editor API)
        if ($configuration->getUseSchemaEditorApi() || !method_exists(Schema::class, 'edit')) {
            return new SchemaTool($entityManager);
        }
        $configuration = clone $configuration;
        $configuration->setUseSchemaEditorApi(true);

        return new SchemaTool(new class($entityManager, $configuration) extends EntityManagerDecorator {
            public function __construct(EntityManagerInterface $wrapped, private readonly Configuration $configuration)
            {
                parent::__construct($wrapped);
            }

            public function getConfiguration(): Configuration
            {
                return $this->configuration;
            }
        });
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
        return self::describe($table, $values, null);
    }

    /**
     * The table, a foreign key the schema editor API left unnamed given the name DBAL's mutable Table gives it
     * (Table::_generateIdentifierName(), "FK_" and the CRC32 of the table's and the local columns' names, cut to
     * the platform's identifier length): the name the SchemaTool without that API gave, and gives on sites.
     *
     * @param (\Closure(Type): ?list<string>)|null $values
     */
    private static function describe(SchemaTable $table, ?\Closure $values, ?int $length): Table
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
            $name = $foreignKey->getName();
            if ('' === $name && null !== $length) {
                $name = strtoupper(substr('fk_'.implode('', array_map(static fn (string $name): string => dechex(crc32($name)), [$table->getName(), ...$foreignKey->getLocalColumns()])), 0, $length));
            }
            $foreignKeys[$name] = self::foreignKey($foreignKey, $name);
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

    private static function foreignKey(ForeignKeyConstraint $foreignKey, string $name): ForeignKey
    {
        return new ForeignKey(
            $name,
            $foreignKey->getLocalColumns(),
            $foreignKey->getForeignTableName(),
            $foreignKey->getForeignColumns(),
            self::action($foreignKey, 'onDelete'),
            self::action($foreignKey, 'onUpdate'),
        );
    }

    /**
     * The key's ON DELETE or ON UPDATE as ForeignKey::action() writes it for the description built without Doctrine
     * (in upper case, null for RESTRICT and NO ACTION): from DBAL's ReferentialAction since 4.3 (getOnDeleteAction(),
     * getOnUpdateAction(); onDelete() and onUpdate() are deprecated since 4.5), from onDelete() and onUpdate() on
     * 4.2. An action DBAL could not read (it said so with a deprecation) is the option as written, as onDelete() and
     * onUpdate() answered.
     *
     * @param 'onDelete'|'onUpdate' $event
     */
    private static function action(ForeignKeyConstraint $foreignKey, string $event): ?string
    {
        // @phpstan-ignore function.alreadyNarrowedType (DBAL 4.2, which composer.json still allows, has no referential actions)
        if (method_exists($foreignKey, 'getOnDeleteAction')) {
            try {
                return ForeignKey::action(('onDelete' === $event ? $foreignKey->getOnDeleteAction() : $foreignKey->getOnUpdateAction())->value);
            } catch (InvalidState) {
            }
        }

        return ForeignKey::action('onDelete' === $event ? $foreignKey->onDelete() : $foreignKey->onUpdate());
    }
}
