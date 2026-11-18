# Changelog

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
