# Changelog

All notable changes are documented here, per release tag (`v1.0.0`, `v1.1.0`... on the `1.x` branch). The
releases are made by `.github/workflows/release.yml` from this file: the coming changes stay under
`## [Unreleased]` until a version takes them.

## [Unreleased]

## [1.0.0] - 2026-11-10

What 1.0 holds, gathered for its first release candidate.

### Added

- `NamingStrategy`: Doctrine's naming strategy on omnisense/omnibridge's naming (`App\Entity\Thread\Tag` is
  `threadTag`, `Thread::$tags` the join table `thread_tags (thread_id, threadTag_id)`), checked against every table
  of an omnibase site; omnibase's own naming strategy extends it.
- `Types::register()`: `datetime` and `datetimetz` written and read in UTC, `utc_datetime` and
  `utc_datetime_immutable`; an unreadable date refused as DBAL 4 refuses it (`InvalidFormat`).
- `EntityManagers::create()`: an entity manager with those types, attribute mapping and PHP's native lazy
  objects, the metadata kept in memory without a cache, and a `configure:` closure that receives the configuration
  before the entity manager exists.
- `DeclarationDriver`: a mapping driver that builds a class's metadata from its declaration
  (`Omnibridge\Schema\Declaration`), the same metadata Doctrine's attribute driver builds.
- `MetadataDescription::of()`: what an entity manager maps, in omnisense/omnibridge's neutral `Description`.
- `Omnibridge\Doctrine\Omnibase\`: omnisense/omnibase's persistence on Doctrine, moved from the core by its plan
  "omnibase agnostic" (steps F3 to S): the adapters of omnibridge's persistence contracts (`DoctrineUnitOfWork`,
  `DoctrineBackend`, `DoctrineRepositories`, `DoctrineShapes`, `QueryPages`), the behaviours' Doctrine backends,
  the mapping, types, walkers, filters and middleware, the finder language's compiler and the core's repositories,
  the Doctrine listeners, and the console's Doctrine tasks. Analysed by omnisense/omnibase's PHPStan, which
  installs the core.

### Fixed

- `StartingWith`, `EndingWith` and their `Not` read their value literally (`LIKE ? ESCAPE '!'`).
- `MetadataSnapshot` leaves out a completor's null values on 5.x only.
