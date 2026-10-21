<?php

namespace Omnibridge\Doctrine;

use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping;
use Doctrine\ORM\Mapping\Builder\EntityListenerBuilder;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Omnibridge\Schema\Declaration\Attribute;
use Omnibridge\Schema\Declaration\AttributeReader;
use Omnibridge\Schema\Declaration\ClassDeclaration;
use Omnibridge\Schema\Declaration\Declarations;
use Omnibridge\Schema\Declaration\Member;
use Omnibridge\Schema\Declaration\Vocabulary;

/**
 * Doctrine's metadata of a class built from its declaration
 * (Omnibridge\Schema\Declaration: what the class declares of its mapping, read
 * without the ORM), no longer from its attributes: Doctrine reads the
 * structure described outside of it (Marco's bridge principle, 2026-10-19;
 * audit "lesringostars face au master plan", R14).
 *
 * The declaration is read as doctrine/orm 3's AttributeDriver reads the
 * attributes - the same calls on the ClassMetadata, in the same order, the
 * attributes instantiated from their arguments as written - so the metadata
 * are those it would build, to the property (the proof: omniscient/omnibase's
 * MetadataSnapshot, every class of lesringostars, on both hosts). Which
 * classes exist, and whether one is transient, stays the driver's it wraps
 * (an AttributeDriver's folders, DoctrineBundle's chain); a class its
 * declarations do not hold (an XML mapping) is left to that driver.
 */
final class DeclarationDriver implements MappingDriver
{
    public function __construct(
        private readonly MappingDriver $driver,
        private readonly Declarations $declarations = new AttributeReader(),
    ) {
    }

    public function getDriver(): MappingDriver
    {
        return $this->driver;
    }

    public function getDeclarations(): Declarations
    {
        return $this->declarations;
    }

    public function getAllClassNames(): array
    {
        return $this->driver->getAllClassNames();
    }

    public function isTransient(string $className): bool
    {
        return $this->driver->isTransient($className);
    }

    /** What else the wrapped driver answers (MappingDriverChain::getDrivers()...). */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->driver->$method(...$arguments);
    }

    public function loadMetadataForClass(string $className, PersistenceClassMetadata $metadata): void
    {
        \assert($metadata instanceof ClassMetadata);
        $declaration = $this->declarations->read($metadata->getReflectionClass() ?? new \ReflectionClass($metadata->name));

        if (null === $declaration) {
            $this->driver->loadMetadataForClass($className, $metadata);

            return;
        }

        $this->load($className, $declaration, $metadata);
    }

    /** AttributeDriver::loadMetadataForClass() over the declaration. */
    private function load(string $className, ClassDeclaration $declaration, ClassMetadata $metadata): void
    {
        $class = self::instances($declaration->attributes);

        if (isset($class[Mapping\Entity::class])) {
            $entity = $class[Mapping\Entity::class];
            if (null !== $entity->repositoryClass) {
                $metadata->setCustomRepositoryClass($entity->repositoryClass);
            }

            if ($entity->readOnly) {
                $metadata->markReadOnly();
            }
        } elseif (isset($class[Mapping\MappedSuperclass::class])) {
            $metadata->setCustomRepositoryClass($class[Mapping\MappedSuperclass::class]->repositoryClass);
            $metadata->isMappedSuperclass = true;
        } elseif (isset($class[Mapping\Embeddable::class])) {
            $metadata->isEmbeddedClass = true;
        } else {
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($className);
        }

        $primaryTable = [];

        if (isset($class[Mapping\Table::class])) {
            $table = $class[Mapping\Table::class];
            $primaryTable['name'] = $table->name;
            $primaryTable['schema'] = $table->schema;

            if ($table->options) {
                $primaryTable['options'] = $table->options;
            }
        }

        if (isset($class[Mapping\Index::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\Index::class);
            }

            foreach ($class[Mapping\Index::class] as $position => $attribute) {
                $index = [];

                if (!empty($attribute->columns)) {
                    $index['columns'] = $attribute->columns;
                }

                if (!empty($attribute->fields)) {
                    $index['fields'] = $attribute->fields;
                }

                if (isset($index['columns'], $index['fields']) || (!isset($index['columns']) && !isset($index['fields']))) {
                    throw MappingException::invalidIndexConfiguration($className, (string) ($attribute->name ?? $position));
                }

                if (!empty($attribute->flags)) {
                    $index['flags'] = $attribute->flags;
                }

                if (!empty($attribute->options)) {
                    $index['options'] = $attribute->options;
                }

                if (!empty($attribute->name)) {
                    $primaryTable['indexes'][$attribute->name] = $index;
                } else {
                    $primaryTable['indexes'][] = $index;
                }
            }
        }

        if (isset($class[Mapping\UniqueConstraint::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\UniqueConstraint::class);
            }

            foreach ($class[Mapping\UniqueConstraint::class] as $position => $attribute) {
                $unique = [];

                if (!empty($attribute->columns)) {
                    $unique['columns'] = $attribute->columns;
                }

                if (!empty($attribute->fields)) {
                    $unique['fields'] = $attribute->fields;
                }

                if (isset($unique['columns'], $unique['fields']) || (!isset($unique['columns']) && !isset($unique['fields']))) {
                    throw MappingException::invalidUniqueConstraintConfiguration($className, (string) ($attribute->name ?? $position));
                }

                if (!empty($attribute->options)) {
                    $unique['options'] = $attribute->options;
                }

                if (!empty($attribute->name)) {
                    $primaryTable['uniqueConstraints'][$attribute->name] = $unique;
                } else {
                    $primaryTable['uniqueConstraints'][] = $unique;
                }
            }
        }

        $metadata->setPrimaryTable($primaryTable);

        if (isset($class[Mapping\Cache::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\Cache::class);
            }

            $cache = $class[Mapping\Cache::class];
            $metadata->enableCache([
                'region' => $cache->region,
                'usage' => \constant(ClassMetadata::class.'::CACHE_USAGE_'.$cache->usage),
            ]);
        }

        if (isset($class[Mapping\InheritanceType::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\InheritanceType::class);
            }

            $metadata->setInheritanceType(\constant(ClassMetadata::class.'::INHERITANCE_TYPE_'.$class[Mapping\InheritanceType::class]->value));

            if (ClassMetadata::INHERITANCE_TYPE_NONE !== $metadata->inheritanceType) {
                if (isset($class[Mapping\DiscriminatorColumn::class])) {
                    $column = $class[Mapping\DiscriminatorColumn::class];
                    $definition = [
                        'name' => $column->name,
                        'type' => $column->type ?? 'string',
                        'length' => $column->length ?? 255,
                        'columnDefinition' => $column->columnDefinition,
                        'enumType' => $column->enumType,
                    ];

                    if ($column->options) {
                        $definition['options'] = $column->options;
                    }

                    $metadata->setDiscriminatorColumn($definition);
                } else {
                    $metadata->setDiscriminatorColumn(['name' => 'dtype', 'type' => 'string', 'length' => 255]);
                }

                if (isset($class[Mapping\DiscriminatorMap::class])) {
                    $metadata->setDiscriminatorMap($class[Mapping\DiscriminatorMap::class]->value);
                }
            }
        }

        if (isset($class[Mapping\ChangeTrackingPolicy::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\ChangeTrackingPolicy::class);
            }

            $metadata->setChangeTrackingPolicy(\constant(ClassMetadata::class.'::CHANGETRACKING_'.$class[Mapping\ChangeTrackingPolicy::class]->value));
        }

        foreach ($declaration->properties as $property) {
            if (self::isRepeatedPropertyDeclaration($property, $metadata)) {
                continue;
            }

            $this->property($className, $property, $metadata);
        }

        if (isset($class[Mapping\AssociationOverrides::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\AssociationOverride::class);
            }

            foreach ($class[Mapping\AssociationOverrides::class]->overrides as $associationOverride) {
                $override = [];
                $fieldName = $associationOverride->name;

                if ($associationOverride->joinColumns) {
                    $override['joinColumns'] = array_map(self::joinColumnToArray(...), $associationOverride->joinColumns);
                }

                if ($associationOverride->inverseJoinColumns) {
                    $override['inverseJoinColumns'] = array_map(self::joinColumnToArray(...), $associationOverride->inverseJoinColumns);
                }

                if ($associationOverride->joinTable) {
                    $override['joinTable'] = [
                        'name' => $associationOverride->joinTable->name,
                        'schema' => $associationOverride->joinTable->schema,
                        'joinColumns' => $override['joinColumns'] ?? [],
                        'inverseJoinColumns' => $override['inverseJoinColumns'] ?? [],
                    ];

                    unset($override['joinColumns'], $override['inverseJoinColumns']);
                }

                if ($associationOverride->inversedBy) {
                    $override['inversedBy'] = $associationOverride->inversedBy;
                }

                if ($associationOverride->fetch) {
                    $override['fetch'] = \constant(ClassMetadata::class.'::FETCH_'.$associationOverride->fetch);
                }

                $metadata->setAssociationOverride($fieldName, $override);
            }
        }

        if (isset($class[Mapping\AttributeOverrides::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\AttributeOverrides::class);
            }

            foreach ($class[Mapping\AttributeOverrides::class]->overrides as $attributeOverride) {
                $metadata->setAttributeOverride($attributeOverride->name, self::columnToArray($attributeOverride->name, $attributeOverride->column));
            }
        }

        if (isset($class[Mapping\EntityListeners::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\EntityListeners::class);
            }

            foreach ($class[Mapping\EntityListeners::class]->value as $item) {
                $listenerClassName = $metadata->fullyQualifiedClassName($item);

                if (!class_exists($listenerClassName)) {
                    throw MappingException::entityListenerClassNotFound($listenerClassName, $className);
                }

                // A listener is behaviour, not structure: its methods are read where they are, as Doctrine reads them.
                $hasMapping = false;
                foreach ((new \ReflectionClass($listenerClassName))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    $callbacks = self::callbacks(new Member($method->name, $method->class, self::listenerAttributes($method)));
                    $hasMapping = $hasMapping ?: !empty($callbacks);

                    foreach ($callbacks as [$methodName, $event]) {
                        $metadata->addEntityListener($event, $listenerClassName, $methodName);
                    }
                }

                if (!$hasMapping) {
                    EntityListenerBuilder::bindEntityListener($metadata, $listenerClassName);
                }
            }
        }

        if (isset($class[Mapping\HasLifecycleCallbacks::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\HasLifecycleCallbacks::class);
            }

            foreach ($declaration->methods as $method) {
                foreach (self::callbacks($method) as [$methodName, $event]) {
                    $metadata->addLifecycleCallback($methodName, $event);
                }
            }
        }
    }

    /** One property of the declaration: a column, a relation or an embedded object (or nothing Doctrine maps). */
    private function property(string $className, Member $property, ClassMetadata $metadata): void
    {
        $attributes = self::instances($property->attributes);
        $mapping = ['fieldName' => $property->name];

        if (isset($attributes[Mapping\Cache::class])) {
            $mapping['cache'] = $metadata->getAssociationCacheDefaults($mapping['fieldName'], [
                'usage' => (int) \constant(ClassMetadata::class.'::CACHE_USAGE_'.$attributes[Mapping\Cache::class]->usage),
                'region' => $attributes[Mapping\Cache::class]->region,
            ]);
        }

        $joinColumns = array_map(self::joinColumnToArray(...), $attributes[Mapping\JoinColumn::class] ?? []);

        if (isset($attributes[Mapping\Column::class])) {
            $mapping = self::columnToArray($property->name, $attributes[Mapping\Column::class]);

            if (isset($attributes[Mapping\Id::class])) {
                $mapping['id'] = true;
                $mapping['idPosition'] = $attributes[Mapping\Id::class]->position;
            }

            if (isset($attributes[Mapping\GeneratedValue::class])) {
                $metadata->setIdGeneratorType(\constant(ClassMetadata::class.'::GENERATOR_TYPE_'.$attributes[Mapping\GeneratedValue::class]->strategy));
            }

            if (isset($attributes[Mapping\Version::class])) {
                $metadata->setVersionMapping($mapping);
            }

            $metadata->mapField($mapping);

            if (isset($attributes[Mapping\SequenceGenerator::class])) {
                $sequence = $attributes[Mapping\SequenceGenerator::class];
                $metadata->setSequenceGeneratorDefinition([
                    'sequenceName' => $sequence->sequenceName,
                    'allocationSize' => $sequence->allocationSize,
                    'initialValue' => $sequence->initialValue,
                ]);
            } elseif (isset($attributes[Mapping\CustomIdGenerator::class])) {
                $metadata->setCustomGeneratorDefinition(['class' => $attributes[Mapping\CustomIdGenerator::class]->class]);
            }
        } elseif (isset($attributes[Mapping\OneToOne::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\OneToOne::class);
            }

            $oneToOne = $attributes[Mapping\OneToOne::class];
            if (isset($attributes[Mapping\Id::class])) {
                $mapping['id'] = true;
                $mapping['idPosition'] = $attributes[Mapping\Id::class]->position;
            }

            $mapping['targetEntity'] = $oneToOne->targetEntity;
            $mapping['joinColumns'] = $joinColumns;
            $mapping['mappedBy'] = $oneToOne->mappedBy;
            $mapping['inversedBy'] = $oneToOne->inversedBy;
            $mapping['cascade'] = $oneToOne->cascade;
            $mapping['orphanRemoval'] = $oneToOne->orphanRemoval;
            $mapping['fetch'] = self::fetchMode($className, $oneToOne->fetch);
            $metadata->mapOneToOne($mapping);
        } elseif (isset($attributes[Mapping\OneToMany::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\OneToMany::class);
            }

            $oneToMany = $attributes[Mapping\OneToMany::class];
            $mapping['mappedBy'] = $oneToMany->mappedBy;
            $mapping['targetEntity'] = $oneToMany->targetEntity;
            $mapping['cascade'] = $oneToMany->cascade;
            $mapping['indexBy'] = $oneToMany->indexBy;
            $mapping['orphanRemoval'] = $oneToMany->orphanRemoval;
            $mapping['fetch'] = self::fetchMode($className, $oneToMany->fetch);

            if (isset($attributes[Mapping\OrderBy::class])) {
                $mapping['orderBy'] = $attributes[Mapping\OrderBy::class]->value;
            }

            $metadata->mapOneToMany($mapping);
        } elseif (isset($attributes[Mapping\ManyToOne::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\ManyToOne::class);
            }

            $manyToOne = $attributes[Mapping\ManyToOne::class];
            if (isset($attributes[Mapping\Id::class])) {
                $mapping['id'] = true;
                $mapping['idPosition'] = $attributes[Mapping\Id::class]->position;
            }

            $mapping['joinColumns'] = $joinColumns;
            $mapping['cascade'] = $manyToOne->cascade;
            $mapping['inversedBy'] = $manyToOne->inversedBy;
            $mapping['targetEntity'] = $manyToOne->targetEntity;
            $mapping['fetch'] = self::fetchMode($className, $manyToOne->fetch);
            $metadata->mapManyToOne($mapping);
        } elseif (isset($attributes[Mapping\ManyToMany::class])) {
            if ($metadata->isEmbeddedClass) {
                throw MappingException::invalidAttributeOnEmbeddable($metadata->name, Mapping\ManyToMany::class);
            }

            $joinTable = [];
            if (isset($attributes[Mapping\JoinTable::class])) {
                $table = $attributes[Mapping\JoinTable::class];
                $joinTable = ['name' => $table->name, 'schema' => $table->schema];

                if ($table->options) {
                    $joinTable['options'] = $table->options;
                }

                if (null !== $table->foreignKeyName) {
                    $joinTable['foreignKeyName'] = $table->foreignKeyName;
                }

                if (null !== $table->inverseForeignKeyName) {
                    $joinTable['inverseForeignKeyName'] = $table->inverseForeignKeyName;
                }

                foreach ($table->joinColumns as $joinColumn) {
                    $joinTable['joinColumns'][] = self::joinColumnToArray($joinColumn);
                }

                foreach ($table->inverseJoinColumns as $joinColumn) {
                    $joinTable['inverseJoinColumns'][] = self::joinColumnToArray($joinColumn);
                }
            }

            foreach ($attributes[Mapping\JoinColumn::class] ?? [] as $joinColumn) {
                $joinTable['joinColumns'][] = self::joinColumnToArray($joinColumn);
            }

            foreach ($attributes[Mapping\InverseJoinColumn::class] ?? [] as $joinColumn) {
                $joinTable['inverseJoinColumns'][] = self::joinColumnToArray($joinColumn);
            }

            $manyToMany = $attributes[Mapping\ManyToMany::class];
            $mapping['joinTable'] = $joinTable;
            $mapping['targetEntity'] = $manyToMany->targetEntity;
            $mapping['mappedBy'] = $manyToMany->mappedBy;
            $mapping['inversedBy'] = $manyToMany->inversedBy;
            $mapping['cascade'] = $manyToMany->cascade;
            $mapping['indexBy'] = $manyToMany->indexBy;
            $mapping['orphanRemoval'] = $manyToMany->orphanRemoval;
            $mapping['fetch'] = self::fetchMode($className, $manyToMany->fetch);

            if (isset($attributes[Mapping\OrderBy::class])) {
                $mapping['orderBy'] = $attributes[Mapping\OrderBy::class]->value;
            }

            $metadata->mapManyToMany($mapping);
        } elseif (isset($attributes[Mapping\Embedded::class])) {
            $mapping['class'] = $attributes[Mapping\Embedded::class]->class;
            $mapping['columnPrefix'] = $attributes[Mapping\Embedded::class]->columnPrefix;

            $metadata->mapEmbedded($mapping);
        }
    }

    /**
     * The attributes as Doctrine's reader hands them out: by class, an instance (the last one written) or, for a
     * repeatable attribute, the list of them in order.
     *
     * @param list<Attribute> $attributes
     *
     * @return array<class-string, object|list<object>>
     */
    private static function instances(array $attributes): array
    {
        $instances = [];
        foreach ($attributes as $attribute) {
            $className = Vocabulary::NAMESPACE.$attribute->name;
            if (Vocabulary::isRepeatable($attribute->name)) {
                $instances[$className][] = self::instance($attribute);
            } else {
                $instances[$className] = self::instance($attribute);
            }
        }

        return $instances;
    }

    /** The attribute's object, built from its arguments as written (a nested attribute built in turn). */
    private static function instance(Attribute $attribute): object
    {
        $className = Vocabulary::NAMESPACE.$attribute->name;

        return new $className(...array_map(self::argument(...), $attribute->arguments));
    }

    private static function argument(mixed $value): mixed
    {
        if ($value instanceof Attribute) {
            return self::instance($value);
        }

        return \is_array($value) ? array_map(self::argument(...), $value) : $value;
    }

    /** @return list<Attribute> a listener method's lifecycle attributes */
    private static function listenerAttributes(\ReflectionMethod $method): array
    {
        $attributes = [];
        foreach ($method->getAttributes() as $attribute) {
            if (null !== ($name = Vocabulary::nameOf($attribute->getName())) && isset(Vocabulary::CALLBACKS[$name])) {
                $attributes[] = new Attribute($name);
            }
        }

        return $attributes;
    }

    /**
     * The lifecycle events a method answers, in the order of Doctrine's driver (prePersist, postPersist, preUpdate...).
     *
     * @return list<array{string, string}>
     */
    private static function callbacks(Member $method): array
    {
        // Doctrine's reader keeps one attribute of a name: one callback each, in the order first written.
        $callbacks = [];
        foreach ($method->attributes as $attribute) {
            if (isset(Vocabulary::CALLBACKS[$attribute->name])) {
                $callbacks[$attribute->name] ??= [$method->name, \constant(Events::class.'::'.Vocabulary::CALLBACKS[$attribute->name])];
            }
        }

        return array_values($callbacks);
    }

    /** ReflectionBasedDriver::isRepeatedPropertyDeclaration(): a property the parent's metadata already maps where it is declared. */
    private static function isRepeatedPropertyDeclaration(Member $property, ClassMetadata $metadata): bool
    {
        $declaringClass = $property->class;

        if (isset($metadata->fieldMappings[$property->name]->declared) && $metadata->fieldMappings[$property->name]->declared === $declaringClass) {
            return true;
        }

        if (isset($metadata->associationMappings[$property->name]->declared) && $metadata->associationMappings[$property->name]->declared === $declaringClass) {
            return true;
        }

        return isset($metadata->embeddedClasses[$property->name]->declared) && $metadata->embeddedClasses[$property->name]->declared === $declaringClass;
    }

    private static function fetchMode(string $className, string $fetchMode): int
    {
        if (!\defined(ClassMetadata::class.'::FETCH_'.$fetchMode)) {
            throw MappingException::invalidFetchMode($className, $fetchMode);
        }

        return \constant(ClassMetadata::class.'::FETCH_'.$fetchMode);
    }

    private static function generatedMode(string $generatedMode): int
    {
        if (!\defined(ClassMetadata::class.'::GENERATED_'.$generatedMode)) {
            throw MappingException::invalidGeneratedMode($generatedMode);
        }

        return \constant(ClassMetadata::class.'::GENERATED_'.$generatedMode);
    }

    /** @return array<string, mixed> */
    private static function joinColumnToArray(Mapping\JoinColumn|Mapping\InverseJoinColumn $joinColumn): array
    {
        $mapping = [
            'name' => $joinColumn->name,
            'deferrable' => $joinColumn->deferrable,
            'unique' => $joinColumn->unique,
            'nullable' => $joinColumn->nullable,
            'onDelete' => $joinColumn->onDelete,
            'columnDefinition' => $joinColumn->columnDefinition,
            'referencedColumnName' => $joinColumn->referencedColumnName,
            'foreignKeyName' => $joinColumn->foreignKeyName,
        ];

        if ($joinColumn->options) {
            $mapping['options'] = $joinColumn->options;
        }

        return $mapping;
    }

    /** @return array<string, mixed> */
    private static function columnToArray(string $fieldName, Mapping\Column $column): array
    {
        $mapping = [
            'fieldName' => $fieldName,
            'type' => $column->type,
            'scale' => $column->scale,
            'length' => $column->length,
            'unique' => $column->unique,
            'nullable' => $column->nullable,
            'index' => $column->index,
            'precision' => $column->precision,
        ];

        if ($column->options) {
            $mapping['options'] = $column->options;
        }

        if (isset($column->name)) {
            $mapping['columnName'] = $column->name;
        }

        if (isset($column->columnDefinition)) {
            $mapping['columnDefinition'] = $column->columnDefinition;
        }

        if (false === $column->updatable) {
            $mapping['notUpdatable'] = true;
        }

        if (false === $column->insertable) {
            $mapping['notInsertable'] = true;
        }

        if (null !== $column->generated) {
            $mapping['generated'] = self::generatedMode($column->generated);
        }

        if ($column->enumType) {
            $mapping['enumType'] = $column->enumType;
        }

        return $mapping;
    }
}
