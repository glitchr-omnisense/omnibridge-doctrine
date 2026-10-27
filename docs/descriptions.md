---
title: Descriptions
order: 30
---

# Descriptions

`Omnibridge\Doctrine\MetadataDescription` (final) writes what an entity manager maps in the neutral
form, `Omnibridge\Schema\Description` (the core's [schema](https://github.com/glitchr-omnisense/omnibridge/blob/1.x/docs/schema.md#the-description)):
each class as its metadata hold it, and the tables its `SchemaTool` would create.

```php
MetadataDescription::of(
    EntityManagerInterface $entityManager,
    ?array $classNames = null,     // the classes; those the metadata driver knows when null
    ?\Closure $values = null,      // fn (Doctrine\DBAL\Types\Type $type): ?list<string> - an enumeration's or a set's values
): Description
```

| Static method | Returns |
|---|---|
| `of($entityManager, $classNames, $values)` | the whole description: the classes sorted, each `entity()`; the tables of `SchemaTool::getSchemaFromMetadata()` over the entities (not the mapped superclasses nor the embeddables), each `table()`, sorted by name |
| `entity(ClassMetadata $metadata)` | one `Entity`: table (null for a mapped superclass or an embeddable), kind, parent (`parentClasses[0]`), root (when not itself), inheritance (type, discriminator column, map sorted, value), identifier, fields, relations, subclasses sorted |
| `table(Doctrine\DBAL\Schema\Table $table, ?\Closure $values = null)` | one `Table`: columns in order (type by DBAL's name, nullable, length, precision, scale, fixed, unsigned, auto-increment, a scalar default, the values `$values` gives), primary key, indexes but the primary (flags lower case), foreign keys |

What is written as Doctrine keeps it, and what is normalised so the two descriptions compare:

- the generator: `auto`, `sequence`, `identity`, `custom`, on the identifier fields only; none is null;
- an `OrderBy` direction as `ASC` or `DESC`, where Doctrine keeps its `Order` enumeration (a backed
  enumeration by its value, `Order::Ascending` by its case);
- DBAL's scale of 0 where no precision was said is left out (null);
- a foreign key's `NO ACTION` is null, other rules upper case;
- a join column's nullable defaults to true when Doctrine keeps null.

## Comparing descriptions

```php
use Omnibridge\Doctrine\MetadataDescription;
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Database\MySqlSchema;

$doctrine = MetadataDescription::of($entityManager);
$neutral = (new Builder(rules: $rules))->build($classes);

$doctrine->toJson() === $neutral->toJson();                          // Doctrine and the builder agree
MySqlSchema::read($pdo, ignore: ['doctrine_migration_versions'])->differences($doctrine);   // [] when the database is the schema
```

`DescriptionTest` proves the builder's description equal to `MetadataDescription`'s, entity by entity
and table by table, through the attribute driver and through the `DeclarationDriver`, and the tables
`SchemaTool` creates on SQLite equal to the description's. omnisense/omnibase's harness and
lesringostars run the same comparison over their own entities; lesringostars compares the result with
its MySQL database's `information_schema`. The [Installation](installation.md) page's script makes
the comparison on two classes.
