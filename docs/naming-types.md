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

`Types::register()` registers the DBAL types of omnibase's schema, as omnibase registers them
(`BaseBundle::boot()`, `BaseExtension::prependDoctrineTypes()`); call it before any metadata is read
(a field's type is asked while it is). `EntityManagers::create()` calls it.

| Type name | Class | Behaviour |
|---|---|---|
| `datetime`, `datetimetz` (overridden) | `Type\DateTimeType` (extends DBAL's `DateTimeType`) | written in UTC - the `\DateTime` given is moved to UTC in place (`setTimezone()` on the object itself) -, read as UTC; an unreadable value is DBAL 4's `InvalidFormat` (a `ConversionException`) |
| `utc_datetime` | `Type\UtcDateTimeType` | a `\DateTime` written in UTC (a copy moved, the value given untouched), read in UTC by the platform's format, else by `date_create()`, else DBAL's own conversion |
| `utc_datetime_immutable` | `Type\UtcDateTimeImmutableType` | the same with `\DateTimeImmutable` |

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

1. `Types::register()`.
2. `ORMSetup::createAttributeMetadataConfig($paths, $development, null, $cache ?? new ArrayAdapter())`:
   the attribute driver over the folders; without a cache the metadata are kept in memory for the
   process (ORMSetup's own default, outside development, looks for APCu, Memcached, then a Redis on
   127.0.0.1).
3. `setNamingStrategy(new NamingStrategy())`.
4. `enableNativeLazyObjects(true)` on PHP 8.4 and later, when the configuration has the method.
5. `$configure($configuration)`: what can no longer change once the entity manager exists - a
   repository factory, filters, DQL functions, the driver wrapped in a `DeclarationDriver`.
6. `new EntityManager($connection, $configuration)`.
