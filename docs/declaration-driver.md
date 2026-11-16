---
title: The declaration driver
order: 20
---

# The declaration driver

`Omnibridge\Doctrine\DeclarationDriver` (final) implements Doctrine's `MappingDriver`: Doctrine's
metadata of a class built from its declaration (`Omnibridge\Schema\Declaration\ClassDeclaration`,
read without the ORM), no longer from its attributes - Doctrine reads the structure described outside
of it (the bridge principle of 2026-10-19).

```php
new DeclarationDriver(
    MappingDriver $driver,                               // the application's: an AttributeDriver, DoctrineBundle's chain
    Declarations $declarations = new AttributeReader(),  // or a DeclarationSet read from a file
);
```

| Method | What it does |
|---|---|
| `getAllClassNames()` | the wrapped driver's classes, listed as it lists them (a chain by namespace; an attribute driver's folders: each file included, the classes PHP declared from them, its class locator's), each kept when `isTransient()` says no |
| `isTransient($className)` | `false` for a class whose declaration is an `Entity` or a `MappedSuperclass`; otherwise the wrapped driver's |
| `loadMetadataForClass($className, $metadata)` | reads the class's declaration; none (an XML mapping, a class the declaration source does not hold) leaves the class to the wrapped driver; otherwise maps it as below |
| `getDriver()`, `getDeclarations()` | what it was built with |
| `__call()` | any other method of the wrapped driver (`MappingDriverChain::getDrivers()`...) |

## What it maps

The declaration is read as doctrine/orm 3's `AttributeDriver::loadMetadataForClass()` reads the
attributes - the same calls on the `ClassMetadata`, in the same order - with each attribute
instantiated from its arguments as written (a nested one, a `JoinTable`'s columns, in turn), so the
classes of `Doctrine\ORM\Mapping` must be installed here (they are: doctrine/orm is required).

The classes may be written in omnibase's own vocabulary (`use Base\Mapping as ORM;`, the core's
`src/Mapping`: the same attributes, parameters and defaults - decision Q8) or in Doctrine's: the
declaration holds the attributes' short names either way, and the driver builds Doctrine's own
attribute objects from them. Doctrine's `AttributeDriver` knows no `Base\Mapping\Entity` and calls such
a class transient; that is why the classes and their transience are the declarations' answer here,
the wrapped driver keeping only which folders (or which class locator) hold them.

- The class: `Entity` (repository class, read-only), `MappedSuperclass` (repository class),
  `Embeddable`; otherwise `MappingException::classIsNotAValidEntityOrMappedSuperClass()`, as Doctrine.
- The table: `Table` (name, schema, options), `Index` and `UniqueConstraint` (columns or fields -
  both or neither is `MappingException::invalidIndexConfiguration()` /
  `invalidUniqueConstraintConfiguration()` -, flags, options), `Cache`.
- The hierarchy: `InheritanceType`, `DiscriminatorColumn`, `DiscriminatorMap`; `ChangeTrackingPolicy`.
- Each property, those already mapped where they are declared skipped (Doctrine's
  `isRepeatedPropertyDeclaration()`): `Column` with `Id`, `GeneratedValue`, `Version`,
  `SequenceGenerator`, `CustomIdGenerator`; `OneToOne`, `OneToMany`, `ManyToOne`, `ManyToMany` with
  `JoinColumn`s, `JoinTable`, `InverseJoinColumn`s, `OrderBy`, the fetch mode (an unknown one is
  `MappingException::invalidFetchMode()`); `Embedded` (class, column prefix); `Cache` on an association.
- `AssociationOverrides` and `AttributeOverrides`.
- `EntityListeners`: each listener class (missing: `MappingException::entityListenerClassNotFound()`),
  its public methods' lifecycle attributes read on the listener itself - a listener is behaviour,
  not structure, so it is not part of the declaration -, or bound by Doctrine's
  `EntityListenerBuilder::bindEntityListener()` when it carries none.
- `HasLifecycleCallbacks`: the declaration's public methods with lifecycle attributes, one callback per
  attribute name, in the order first written.

An attribute that is invalid on an embeddable (`Index`, `InheritanceType`, an association...) is
`MappingException::invalidAttributeOnEmbeddable()`, as Doctrine.

## Wiring it

With `EntityManagers` (plain PHP, Laravel):

```php
$entityManager = EntityManagers::create($connection, $paths, configure: function ($configuration) {
    $configuration->setMetadataDriverImpl(new DeclarationDriver($configuration->getMetadataDriverImpl()));
});
```

From declarations kept as data (a JSON file written once by `DeclarationSet::toArray()`):

```php
use Omnibridge\Schema\Declaration\DeclarationSet;

$declarations = DeclarationSet::fromArray(json_decode(file_get_contents('var/declarations.json'), true));
$configuration->setMetadataDriverImpl(new DeclarationDriver($configuration->getMetadataDriverImpl(), $declarations));
```

Under Symfony, a compiler pass or a decoration of DoctrineBundle's driver chain service wraps it the
same way; this package ships no bundle.

The proof that the metadata are the attribute driver's: `DeclarationDriverTest` maps eight fixture
classes (a mapped superclass, a joined hierarchy with a generated map, a single-table one with a
declared map, to-one and to-many relations, a nested join table, an enumeration, lifecycle callbacks, a
repository class) both ways and compares them class by class, from the attributes and from
declarations gone through JSON; omnisense/omnibase's `MetadataSnapshot` does it for every class of
lesringostars, on both hosts.
