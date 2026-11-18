<?php

namespace Omnibridge\Doctrine;

use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Column as SchemaColumn;
use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index as SchemaIndex;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table as SchemaTable;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
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
        $connection = $entityManager->getConnection();
        $length = $connection->getDatabasePlatform()->getMaxIdentifierLength();
        $configuration = $connection->getConfiguration();
        // @phpstan-ignore function.alreadyNarrowedType (DBAL 4.2 to 4.5, which composer.json still allows, has no type provider)
        $types = method_exists($configuration, 'getTypeProvider') ? $configuration->getTypeProvider() : null;
        foreach (self::schemaTool($entityManager)->getSchemaFromMetadata($metadata)->getTables() as $table) {
            $description = self::describe($table, $values, $length, $types);
            $tables[$description->name] = $description;
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
        return self::describe($table, $values, null, null);
    }

    /**
     * The table, a foreign key the schema editor API left unnamed given the name DBAL's mutable Table gives it
     * (Table::_generateIdentifierName(), "FK_" and the CRC32 of the table's and the local columns' names, cut to
     * the platform's identifier length): the name the SchemaTool without that API gave, and gives on sites.
     *
     * Read through the object names, the indexed columns, the primary key constraint and the column's type name
     * where DBAL has them (the getters of before are deprecated since DBAL 4.5 and 4.6), with the same names: a
     * name as getName() gave it (the identifier's value, its qualifier's before it), the columns of an index or a key
     * as given to it (Name::toString(), what the schema editor API gives them). A table those can not read (DBAL
     * says so with InvalidState) is read with the getters of before, as on DBAL 4.2.
     *
     * @param (\Closure(Type): ?list<string>)|null $values
     */
    private static function describe(SchemaTable $table, ?\Closure $values, ?int $length, ?TypeProvider $types): Table
    {
        // DBAL 4.2, which composer.json still allows, has none of these.
        // @phpstan-ignore function.alreadyNarrowedType
        $objects = method_exists($table, 'getObjectName')
            // @phpstan-ignore function.alreadyNarrowedType
            && method_exists($table, 'getPrimaryKeyConstraint')
            // @phpstan-ignore function.alreadyNarrowedType
            && method_exists(SchemaIndex::class, 'getIndexedColumns')
            // @phpstan-ignore function.alreadyNarrowedType
            && method_exists(ForeignKeyConstraint::class, 'getReferencingColumnNames');
        if ($objects) {
            try {
                return self::describeObjects($table, $values, $length, $types);
            } catch (InvalidState) {
            }
        }

        return self::describeAssets($table, $values, $length);
    }

    /** @param (\Closure(Type): ?list<string>)|null $values */
    private static function describeObjects(SchemaTable $table, ?\Closure $values, ?int $length, ?TypeProvider $types): Table
    {
        $tableName = self::qualified($table->getObjectName());

        $columns = [];
        foreach ($table->getColumns() as $column) {
            $name = self::name($column);
            $columns[$name] = self::column($name, $column, $values, $types);
        }

        $primaryKey = $table->getPrimaryKeyConstraint();
        $primaryColumns = null === $primaryKey ? [] : self::names($primaryKey->getColumnNames());
        // The primary key is one of the table's indexes still (until DBAL 5): the unique one on its columns, named
        // "primary" where it was given no name (all the SchemaTool's), else that index's own name.
        $primaryIndexes = [];
        foreach ($table->getIndexes() as $key => $index) {
            if (null !== $primaryKey && IndexType::UNIQUE === $index->getType() && self::indexed($index) === $primaryColumns) {
                $primaryIndexes[] = $key;
            }
        }
        $primaryIndex = \in_array('primary', $primaryIndexes, true) ? 'primary' : ($primaryIndexes[0] ?? null);

        $indexes = [];
        foreach ($table->getIndexes() as $key => $index) {
            if ($key === $primaryIndex) {
                continue;
            }
            $flags = match ($index->getType()) {
                IndexType::FULLTEXT => ['fulltext'],
                IndexType::SPATIAL => ['spatial'],
                default => [],
            };
            if ($index->isClustered()) {
                $flags[] = 'clustered';
            }
            $name = self::name($index);
            $indexes[$name] = new Index($name, self::indexed($index), IndexType::UNIQUE === $index->getType(), $flags);
        }
        ksort($indexes);

        $foreignKeys = [];
        foreach ($table->getForeignKeys() as $foreignKey) {
            $name = self::name($foreignKey);
            $local = self::names($foreignKey->getReferencingColumnNames());
            if ('' === $name && null !== $length) {
                $name = self::foreignKeyName($tableName, $local, $length);
            }
            $foreignKeys[$name] = new ForeignKey(
                $name,
                $local,
                self::qualified($foreignKey->getReferencedTableName()),
                self::names($foreignKey->getReferencedColumnNames()),
                self::action($foreignKey, 'onDelete'),
                self::action($foreignKey, 'onUpdate'),
            );
        }
        ksort($foreignKeys);

        return new Table($tableName, $columns, $primaryColumns, $indexes, $foreignKeys);
    }

    /**
     * The table read with the getters of before (DBAL 4.2, a table the object names can not read).
     *
     * @param (\Closure(Type): ?list<string>)|null $values
     */
    private static function describeAssets(SchemaTable $table, ?\Closure $values, ?int $length): Table
    {
        $columns = [];
        foreach ($table->getColumns() as $column) {
            $columns[$column->getName()] = self::column($column->getName(), $column, $values, null);
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
                $name = self::foreignKeyName($table->getName(), $foreignKey->getLocalColumns(), $length);
            }
            $foreignKeys[$name] = new ForeignKey(
                $name,
                $foreignKey->getLocalColumns(),
                $foreignKey->getForeignTableName(),
                $foreignKey->getForeignColumns(),
                self::action($foreignKey, 'onDelete'),
                self::action($foreignKey, 'onUpdate'),
            );
        }
        ksort($foreignKeys);

        return new Table($table->getName(), $columns, $table->getPrimaryKey()?->getColumns() ?? [], $indexes, $foreignKeys);
    }

    /** @param list<string> $columns */
    private static function foreignKeyName(string $table, array $columns, int $length): string
    {
        return strtoupper(substr('fk_'.implode('', array_map(static fn (string $name): string => dechex(crc32($name)), [$table, ...$columns])), 0, $length));
    }

    /** A table's name as getName() gave it: the qualifier's value and a dot before the name's. */
    private static function qualified(OptionallyQualifiedName $name): string
    {
        $qualifier = $name->getQualifier();

        return (null === $qualifier ? '' : $qualifier->getValue().'.').$name->getUnqualifiedName()->getValue();
    }

    /**
     * A column's, an index's or a key's name as getName() gave it: the identifier's value, '' for none.
     *
     * @param AbstractAsset<UnqualifiedName> $asset
     */
    private static function name(AbstractAsset $asset): string
    {
        \assert(method_exists($asset, 'getObjectName'));
        $name = $asset->getObjectName();
        \assert(null === $name || $name instanceof UnqualifiedName);

        return null === $name ? '' : $name->getIdentifier()->getValue();
    }

    /**
     * @param list<UnqualifiedName> $names
     *
     * @return list<string> the names as given to the index or the key
     */
    private static function names(array $names): array
    {
        return array_map(static fn (UnqualifiedName $name): string => $name->toString(), $names);
    }

    /** @return list<string> */
    private static function indexed(SchemaIndex $index): array
    {
        return array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns());
    }

    /**
     * The column, its type by its name where DBAL keeps it (Column::getTypeName(), 4.6; Column::getType() and
     * Type::lookupName() are deprecated there), the instance $values is given from the connection's types
     * ($types, those of DBAL's registry for a table given alone), as Column::getType() resolved it.
     *
     * @param (\Closure(Type): ?list<string>)|null $values
     */
    private static function column(string $name, SchemaColumn $column, ?\Closure $values, ?TypeProvider $types): Column
    {
        $default = $column->getDefault();
        $precision = $column->getPrecision();
        $scale = $column->getScale();
        // @phpstan-ignore function.alreadyNarrowedType (DBAL 4.2 to 4.5, which composer.json still allows, has no type name)
        $byName = method_exists($column, 'getTypeName');

        return new Column(
            $name,
            $byName ? $column->getTypeName() : Type::lookupName($column->getType()),
            !$column->getNotnull(),
            $column->getLength(),
            $precision,
            null === $precision && 0 === $scale ? null : $scale, // DBAL's column has a scale of 0 where none was said

            $column->getFixed(),
            $column->getUnsigned(),
            $column->getAutoincrement(),
            \is_scalar($default) ? $default : null,
            null !== $values ? $values($byName ? ($types ?? self::registry())->get($column->getTypeName()) : $column->getType()) : null,
        );
    }

    /** DBAL's registry: the type provider of a connection configured without one (Type::getTypeRegistry() is deprecated since 4.6). */
    private static function registry(): TypeProvider
    {
        return (new DbalConfiguration())->getTypeProvider();
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
