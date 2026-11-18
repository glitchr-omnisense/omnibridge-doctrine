# Changelog

## 4.0.0

doctrine/dbal 4.6 and doctrine/orm 3.8's deprecations that 3.0.4 left for a major, out of the bridge: its src/ triggers
none under DBAL 4.6 / ORM 3.8. doctrine/dbal ^4.6 (`Configuration::setTypeProvider()`).

- The date types are immutable. `Type\DateTimeType` (omnibase's `datetime` and `datetimetz`,
  `Base\Database\Type\DateTimeTypeUTC`) extends DBAL's `DateTimeImmutableType` (DBAL 4.6 deprecates the mutable
  `DateTimeType`, DBAL 5 removes it): a column of these types is read as a `\DateTimeImmutable` in UTC. Any
  `\DateTimeInterface` is written, in UTC, and the object given is left as it is (3.x moved the entity's own
  `\DateTime` to UTC in place). `utc_datetime` (`Type\UtcDateTimeType`, `Base\Database\Type\UtcDateTimeType`) is read
  as `utc_datetime_immutable` is. The columns are DATETIME as before: no schema change.
- The types are the connection's. `Types::register()` (both) changed DBAL's static registry, deprecated in DBAL 4.6 and
  gone in 5; `provider()` puts them in a `TypeRegistry` - the one given, or a new one - for the connection's
  configuration. `EntityManagers::create()` gives a connection configured without a provider of its own the bridge's
  (its platform too, when it was resolved already) and the bridge's types to a `TypeRegistry` the host gave it;
  `Omnibase\Mapping::register()` puts omnibase's in the connection's provider. `EnumerationsMiddleware` is removed (a
  DBAL middleware cannot reach the connection's configuration). A `ClassMetadataManipulator` made without an entity
  manager answers an enumeration from `Base\Database\Type\Enumerations` and the core's types from a provider of its own;
  the MySQL schema manager takes the SET type from the connection's types.
- `EntityManagers::create()` turns doctrine/orm 3.8's schema editor API on, and registers `ForeignKeyNames` on
  `ToolEvents::postGenerateSchema`: a foreign key the API leaves unnamed is given the name the SchemaTool gave before
  (`FK_` and the CRC32 of the table's and columns' names), the one the sites' migrations wrote - on the migrated dev
  copies of three sites (123, 147 and 240 keys), the update SQL and every key, index and unique name
  are the same with the API on and off.
- `QueryPages::paginator()` is removed: `slice()` and `count()` page the query, as they did since 3.0.3 (the
  `OffsetPaginator` 3.0.3 announced is not needed for a page).
- The Doctrine-vocabulary test fixture orders by `\SortDirection`; omnibase's vocabulary (`Base\Mapping\OrderBy`) is
  given to Doctrine as `SortDirection` by `DeclarationDriver` since 3.0.4, strings or not.

### UPGRADE from 3.x

- `Omnibridge\Doctrine\Types::register()` -> `Types::provider()`, given to the connection's configuration:
  `DriverManager::getConnection($parameters, (new Configuration())->setTypeProvider(Types::provider()))`.
  `EntityManagers::create()` does it for a connection that has none.
- `Omnibridge\Doctrine\Omnibase\Types::register($types, $directories)` ->
  `Types::provider($types, Types::enumerations($directories), $registry)`: the enumerations found (name => class),
  no longer the folders.
- `Omnibase\Types::registerEnumerations($enumerations, $override)` ->
  `registerEnumerations($registry, $enumerations, $override)`.
- `Omnibase\EnumerationsMiddleware` is gone: give the connection's configuration the provider holding the enumerations.
  A Symfony host does it in omnibridge/symfony (each `doctrine.dbal.<name>_connection.configuration`).
- Code reading DBAL's static registry (`Type::getType()`, `Type::hasType()`, `Type::getTypeRegistry()`) finds none of
  omnibase's types any more: ask the connection's (`$connection->getConfiguration()->getTypeProvider()`).
- An entity property mapped `datetime`, `datetimetz` or `utc_datetime` receives a `\DateTimeImmutable`: a property, a
  getter or a parameter typed `\DateTime` becomes `\DateTimeImmutable` (or `\DateTimeInterface`), code changing the
  value in place (`->modify()`, `->setTime()`) assigns the result instead. Writing a `\DateTime` still works.
- `QueryPages::paginator()` is gone: `$pages->slice(($number - 1) * $size, $size)` for a page's rows,
  `$pages->count()` for the total (`$pages->query($offset, $limit)` for the paged query itself).
- An entity manager built by `EntityManagers::create()` has the schema editor API on; a `configure:` closure may still
  turn it off (deprecated in doctrine/orm 3.8). A host building its own entity manager registers `ForeignKeyNames` on
  `postGenerateSchema` to keep the foreign keys' names.

## 3.0.4

doctrine/dbal 4.5 and 4.6's deprecations out of the bridge, no public signature changed:
- DBAL's types: `Types::register()` (both), the MySQL schema manager and the describer register and override in DBAL's
  registry itself, reached as the type provider of a connection configured without one
  (`Configuration::getTypeProvider()`; `Type::overrideType()`, `addType()`, `hasType()`, `getTypeRegistry()` are
  deprecated in 4.6). Where an entity manager is at hand (the finder's converted types, the column types,
  `ClassMetadataManipulator::getDoctrineType()`, `MetadataSnapshot`) the types are its connection's: the same registry
  unless the host gave the connection a type provider of its own. Before DBAL 4.6, `Type::getTypeRegistry()` as before.
- `MetadataDescription` reads the tables through the object names, the indexed columns and index type, the primary key
  constraint, the foreign keys' referencing and referenced names and the column's type name (`Column::getTypeName()`),
  where DBAL has them; the names are the same (an object's name as `getName()` gave it, the columns as given to the
  index or the key) - the getters of before on DBAL 4.2, or for a table DBAL says it cannot read (`InvalidState`). The
  primary key, still among DBAL's indexes, is the unique index on its columns. `$values` is given the column's type
  from the connection's types (DBAL's registry for `MetadataDescription::table()`).
- The order directions: `DeclarationDriver` gives an `#[OrderBy]`'s "ASC" and "DESC" to Doctrine as `SortDirection`,
  and the finder's ORDER BY passes `SortDirection` to the query builder (doctrine/orm 3.8 deprecates the strings); any
  other value is left to Doctrine, which refuses it as before. The same metadata, the same DQL.
- The MySQL schema manager makes a SET column, or one with a "(DC2Type:...)" comment, again by DBAL's column editor
  (4.5; the column's setters are deprecated there), with the connection's types; set in place before 4.5.
- The slug's "unique alone" check reads the indexed columns and the index type; the primary key is the unique index on
  the identifier's columns.

Left for 4.0 (a public change each): the bridge's `datetime` and `datetimetz` (`Type\DateTimeType`) extend DBAL's
mutable `DateTimeType`, deprecated in DBAL 4.6 for `DateTimeImmutableType`; `Types::register()` changes DBAL's static
registry, which DBAL 5 removes (a type provider given to the connection's configuration instead);
`EntityManagers::create()` leaves doctrine/orm 3.8's schema editor API off.

## 3.0.3

doctrine/orm 3.8's deprecations out of the bridge, no public signature changed:
- `QueryPages` no longer builds Doctrine's `Paginator` (deprecated in 3.8) to page. Its pages never went
  through it - `slice()` runs the paged query with its own window, `count()` the CountWalker clone - so the
  results are the same. `paginator()` keeps its signature and builds the `Paginator` only when it is called; it
  is deprecated, and 4.0 will return 3.8's `OffsetPaginator`.
- `MetadataDescription::of()` reads the tables from a `SchemaTool` on the schema editor API where DBAL has it
  (4.5 and later), on a copy of the entity manager's configuration, which stays as the host set it. A foreign key
  that API leaves unnamed is given the name DBAL's mutable `Table` gave it ("FK_" and the CRC32 of the table's and
  columns' names), so the description is the same.
- Column options: a column's `charset` and `collation` stay in its `options`, as 3.8's `AttributeDriver` writes
  them and as the schema editor API takes them without deprecation; `DeclarationDriver` already passed them so.
- `DeclarationDriver`: a `#[CustomIdGenerator]` without `#[GeneratedValue]` is the `CUSTOM` strategy, as 3.8's
  `AttributeDriver` reads it (`NONE` before); an explicit `#[GeneratedValue(strategy: 'NONE')]` keeps the
  identifier assigned. No entity of the ensemble declares one.

## 3.0.2

Dependencies: this package now requires `doctrine/orm` ^3.8 and `doctrine/orm-plugin-second-level-cache` ^1.0.
Doctrine ORM 3.8 moved the second-level cache out to that plugin, and this package uses the cache
(`DoctrineCacheBackend` evicts through `getCache()`); a site enabling `second_level_cache` failed on 3.8 with
"Using the second level cache requires doctrine/orm-plugin-second-level-cache". The plugin autoloads
`Doctrine\ORM\Cache\`, which ORM before 3.8 still ships, hence the floor at 3.8.

## 3.0.1

Fixes found once omnisense/omnibase's PHPStan analyses this package's src/Base and src/Omnibase (no signature changes):
- `VersionableSubscriber` asks `obsoleteIds()` of the revisions' repository only when it is the core's
  `RevisionRepository` (a repository without it is left alone instead of failing in the finder language).
- `LifecycleRelay` and the repositories' backend check that they are given Doctrine ORM's entity manager and unit of
  work, with a clear error otherwise.
- `MetadataSnapshot` iterates DBAL 4's type registry directly (no `getMap()` there); `ClassMetadataCompletor::__set()`
  returns nothing, as a magic setter does.
- Types: the finders' parameters and bindings already lists, the generic events, metadata and repositories in
  PHPDoc, `DoctrineFinder::find()` given a class name it checked.

## 3.0.0

omnibridge/doctrine: omnibase's persistence on Doctrine ORM 3 and DBAL 4, framework-free - Doctrine reading the
structure described outside of it (omnisense/omnibridge's schema, entities written in `Base\Mapping` or in Doctrine's
own vocabulary, through the `DeclarationDriver` that `EntityManagers::create()` sets), omnibase's naming strategy and
UTC date types, an entity manager built with them; and, behind omnisense/omnibase 7.0's contracts, the engine of its
agnostic repositories: `Finders` (the finder language and `findBy()`'s criteria array compiled to DQL), the
selection the core's common SQL is written around (findValuesBy(), updateBy(), lockOneBy(), sql()...), the
neutral lifecycle relayed to the host's listeners (`Base\Contract\Lifecycle\Listeners::TAG`), the database errors as
the core's (`UniqueConstraintViolation`, `TableNotFound`). It declares no repository: `Base\Repository\*`, a bundle's
and `App\Repository\*` are the same classes on every persistence. The Doctrine classes a host's configuration names
(`Base\Database\Mapping\NamingStrategy`, `Base\Database\Type\*`, `Base\DatabaseSubscriber\*`...) are declared under
the core's `Base\` namespace.
