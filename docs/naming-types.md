---
title: Naming, types, entity managers
order: 40
---

# Naming, types, entity managers

## NamingStrategy

`Omnibridge\Doctrine\NamingStrategy` (not final) implements `Doctrine\ORM\Mapping\NamingStrategy` by
asking a `Omnibridge\Schema\Naming` (default `new CamelCaseNaming()`): `classToTableName()`,
`propertyToColumnName()`, `embeddedFieldToColumnName()`, `referenceColumnName()`, `joinColumnName()`,
`joinTableName()`, `joinKeyColumnName()` each delegate; `naming()` gives the `Naming`. Constants
`TABLE_NAME_SIZE` (64) and `TABLE_I18N_SUFFIX` (`Intl`) are `CamelCaseNaming`'s.

| Doctrine asks | Answer (`CamelCaseNaming`) |
|---|---|
| a class's table | `App\Entity\Thread\Tag` `threadTag`, `App\Entity\User\Profile` `userProfile`; a `#[Table(name)]` kept |
| a property's column | the property, first letter lower case |
| a join column | `<property>_id` |
| a join table | `thread_tags` for `Thread::$tags` |
| a join table's key column | `<table>_id`: `thread_id`, `threadTag_id` |

The rules are the core's ([Naming](https://github.com/glitchr-omnisense/omnibridge/blob/1.x/docs/schema.md#naming)).
`NamingStrategyTest` checks them against every table and join table of lesringostars
(`tests/lesringostars-tables.json`). omnisense/omnibase's `Base\Database\Mapping\NamingStrategy`
extends this class.

```php
$configuration->setNamingStrategy(new Omnibridge\Doctrine\NamingStrategy());
// another prefix list: new NamingStrategy(new CamelCaseNaming(['Acme\\']))
```

## Types

`Types::provider(?TypeRegistry $registry = null): TypeRegistry` puts the DBAL types of omnibase's schema
in a type provider - `$registry`, or a new registry of DBAL's built-in types - and returns it, for the
connection's configuration (4.0; DBAL's static registry, which `Type::addType()` and `overrideType()`
change, is deprecated in DBAL 4.6 and gone in 5):

```php
$connection = DriverManager::getConnection($parameters, (new Doctrine\DBAL\Configuration())->setTypeProvider(Types::provider()));
```

Give it before any metadata is read (a field's type is asked while it is). `EntityManagers::create()`
gives one to a connection that has none of its own. omnibase's types (the core's, the passkeys', the
enumerations) are `Omnibase\Types::provider($types, $enumerations, $registry)`.

| Type name | Class | Behaviour |
|---|---|---|
| `datetime`, `datetimetz` (overridden) | `Type\DateTimeType` (extends DBAL's `DateTimeImmutableType`) | any `\DateTimeInterface` written in UTC (a copy moved, the value given untouched), read as a `\DateTimeImmutable` in UTC; an unreadable value is DBAL 4's `InvalidFormat` (a `ConversionException`) |
| `datetime_immutable`, `datetimetz_immutable` (overridden) | `Type\UtcDateTimeImmutableType` | the same |
| `utc_datetime` | `Type\UtcDateTimeType` | the same (read as a `\DateTimeImmutable` since 4.0: the mutable twin is gone with DBAL's mutable `DateTimeType`), read in UTC by the platform's format, else by `date_create_immutable()`, else DBAL's own conversion |
| `utc_datetime_immutable` | `Type\UtcDateTimeImmutableType` | the same |

The two `utc_*` types are added only when no type of that name exists, and are not final (omnibase's
core extends them). `Type\Utc::zone()` is the shared `UTC` zone. Their `getName()` and
`requiresSQLCommentHint()` answer DBAL 3's questions; DBAL 4 asks neither.

## EntityManagers

```php
EntityManagers::create(
    Doctrine\DBAL\Connection $connection,
    array $paths,                                  // the entities' folders
    bool $development = false,
    ?Psr\Cache\CacheItemPoolInterface $cache = null,
    ?\Closure $configure = null,                   // fn (Doctrine\ORM\Configuration $configuration): void
): Doctrine\ORM\EntityManager
```

1. The connection's types: a connection configured without a provider of its own (DBAL's static
   registry) is given `Types::provider()` - its platform too, when it was resolved already; a
   `TypeRegistry` the host gave it is given the bridge's types; another provider is left as it is.
2. `ORMSetup::createAttributeMetadataConfig($paths, $development, null, $cache ?? new ArrayAdapter())`:
   the attribute driver over the folders; without a cache the metadata are kept in memory for the
   process (ORMSetup's own default, outside development, looks for APCu, Memcached, then a Redis on
   127.0.0.1).
3. `setNamingStrategy(new NamingStrategy())`, and `setUseSchemaEditorApi(true)` (4.0: doctrine/orm 3.8's
   schema tool on DBAL's editors; leaving it off is deprecated there, impossible in ORM 4).
4. `enableNativeLazyObjects(true)` on PHP 8.4 and later, when the configuration has the method.
5. `$configure($configuration)`: what can no longer change once the entity manager exists - a
   repository factory, filters, DQL functions, the driver wrapped in a `DeclarationDriver`.
6. `new EntityManager($connection, $configuration)`.
