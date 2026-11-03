# omnibridge/doctrine

The storage glue of omnibase sites, framework-free (plan 0, rule 13): what any
framework needs to map an omnibase site's database with Doctrine ORM and find
the same tables - and Doctrine reading the structure described outside of it
(audit "lesringostars face au master plan", R14; omnisense/omnibridge's
`Omnibridge\Schema`).

- `DeclarationDriver`: a Doctrine mapping driver that builds a class's metadata
  from its declaration (`Omnibridge\Schema\Declaration`: what the class declares
  of its mapping, read without the ORM - or kept as data, a JSON file) instead
  of reading the attributes itself. It wraps the application's driver (an
  attribute driver, DoctrineBundle's chain), which still says which classes
  exist and maps a class the declarations do not hold. The metadata are those
  Doctrine's own attribute driver builds, to the property (the same calls on
  the ClassMetadata, in the same order): `DeclarationDriverTest` compares every
  class of the fixtures both ways, from the attributes and from declarations
  that went through JSON.
- `MetadataDescription::of($entityManager)`: what an entity manager maps, in the
  neutral form (`Omnibridge\Schema\Description`): each class as its metadata
  hold it, and the tables its SchemaTool would create. `DescriptionTest` proves
  it equal to the description `Omnibridge\Schema\Builder` builds from the
  declarations alone; omnisense/omnibase's harness and lesringostars do the
  same over their entities, and lesringostars compares it with its database's
  `information_schema`.
- `NamingStrategy`: Doctrine's naming strategy answered by the family's naming
  (`Omnibridge\Schema\CamelCaseNaming`, or the `Naming` given): `App\Entity\Thread\Tag`
  is `threadTag`, `App\Entity\User\Profile` `userProfile`, `Thread::$tags` the
  join table `thread_tags (thread_id, threadTag_id)`, a property its own name.
  Checked against every table and join table of lesringostars
  (`tests/lesringostars-tables.json`). omnibase's `Base\Database\Mapping\NamingStrategy`
  extends it: one place says how a class becomes a table.
- `Types::register()`: `datetime` and `datetimetz` written and read in UTC,
  `utc_datetime` and `utc_datetime_immutable`, as omnibase registers them.
- `EntityManagers::create($connection, $paths)`: an entity manager with both,
  attribute mapping, PHP's native lazy objects; its `configure:` closure
  receives the configuration before the entity manager exists (a repository
  factory, the driver wrapped in a `DeclarationDriver`).

```php
use Omnibridge\Doctrine\DeclarationDriver;
use Omnibridge\Doctrine\EntityManagers;

$entityManager = EntityManagers::create($connection, [$projectDir.'/src/Entity'], configure: function ($configuration) {
    $configuration->setMetadataDriverImpl(new DeclarationDriver($configuration->getMetadataDriverImpl()));
});
```

Requires omnisense/omnibridge (the Schema), doctrine/orm ^3.4 and doctrine/dbal
^4.2 (tested on 3.7.5 and 4.5.0).

## omnibase's persistence on Doctrine (`Omnibridge\Doctrine\Omnibase\`)

Since omnisense/omnibase's plan "omnibase agnostic", step F3 (2026-11-02), the core's Doctrine
classes live here, in `src/Omnibase/`: 142 classes moved by one rule - `Base\Bridge\Doctrine\X` is
`Omnibridge\Doctrine\Omnibase\X`, any other `Base\X` is `Omnibridge\Doctrine\Omnibase\X`:

- the Doctrine adapters of omnibridge's persistence contracts (`DoctrineUnitOfWork`,
  `DoctrineBackend`, `DoctrineRepositories`, `DoctrineShapes`, `QueryPages`, `MetadataDescription`'s
  use), the behaviours' Doctrine backends (`Behaviour\*`: row locks, conditional updates, the entity
  cache, bulk deletes, the vault, slugs, cache and order column) and their mapping hooks
  (`Behaviour\DoctrineMappingHooks`: `#[Cache]`'s regions, `#[Vault]`'s unique constraints,
  `#[OrderColumn]`'s field);
- the mapping (`Mapping`, `MappingDriver`, `Configuration`, `Types`, `RepositoryFactory`, `Registry`,
  `MetadataSnapshot`, `Database\Mapping\ClassMetadataManipulator`...), the DBAL types and adapters,
  the DQL walkers and functions, the trash and vault filters, the platform middleware, the schema
  describer;
- the finder language's Doctrine compiler (`Database\Repository\ServiceEntityParser`) and
  `ServiceEntityRepository`, and the core's 81 repositories (`Repository\*`);
- the Doctrine listeners (`DatabaseSubscriber\*`, `EntitySubscriber\{Extension, Trasheable,
  Versionable, Setting}Subscriber`) and `DoctrineLocator` (the registry and entity manager for the
  core's host code).

This part is loaded only with omnisense/omnibase (in `suggest` here, not `require`). omnibase 4.x
requires this package; since omnibase 5.0 the application does (the core suggests it), and this package
requires the DQL functions the part registers, beberlei/doctrineextensions and
scienta/doctrine-json-functions. The core keeps the neutral half - the finder's
parser and AST, the repository interfaces, the shapes, the enumerations, the behaviours'
attributes - and **aliases every former name** (`src/Resources/former-names.php`), so the sites and
bundles change nothing. An entity's `repositoryClass` may name one of the core's repository
interfaces: `RepositoryFactory` builds the repository bound to it. This package's own suite tests the
generic part (and that it names nothing of omnibase, `OmnibasePartTest`); omnibase's part is tested in
the core's harness, which mounts this checkout. PHPStan leaves `src/Omnibase` to the core's analysis.

## glitchr/base-plugin's Doctrine hook (audited 2026-10-17)

The hook rewrites three files of doctrine/orm:

- `ObjectHydrator`: its anchor has been gone since doctrine/orm 3.6.7. The hook no longer patches anything there.
- `SqlWalker` (`private` made `protected`): not needed. omnibase's walkers use public methods only.
- `ClassMetadataFactory` (`private` made `protected`): still needed.
  - omnibase overrides `getShortName()`, private upstream, so that default discriminator maps are keyed by class names. On doctrine/orm as published, its schema creation stops on a duplicated discriminator entry (`user`).
  - It also reads `$em`, which a setter override could replace.

This package needs no patch: an application mapping the tables with its own
entities and explicit discriminator maps runs on doctrine/orm as published.

## Tests

```bash
docker run --rm -v "$PWD":/app -w /app lesringostars-laravel-app vendor/bin/phpunit
```

18 tests: the vocabulary against doctrine/orm's attributes (every parameter, default
and repeatability; every mapping attribute upstream known), the DeclarationDriver's
metadata against the attribute driver's for a joined and a single-table hierarchy,
nested join tables, lifecycle callbacks (from the attributes, and from declarations
gone through JSON), the description built without Doctrine against Doctrine's and
the tables it creates, the naming over lesringostars' tables, the UTC types.
