# omnibridge/doctrine

The storage glue of omnibase sites, framework-free (plan 0, rule 13): what any
framework needs to map an omnibase site's database with Doctrine ORM and find
the same tables.

- `NamingStrategy`: omnibase's names. `App\Entity\Thread\Tag` is `threadTag`,
  `App\Entity\User\Profile` `userProfile`, `Thread::$tags` the join table
  `thread_tags (thread_id, threadTag_id)`, a property its own name. Checked
  against every table and join table of lesringostars
  (`tests/lesringostars-tables.json`).
- `Types::register()`: `datetime` and `datetimetz` written and read in UTC,
  `utc_datetime` and `utc_datetime_immutable`, as omnibase registers them.
- `EntityManagers::create($connection, $paths)`: an entity manager with both,
  attribute mapping, PHP's native lazy objects.

Copied from omnibase (`Base\Database\Mapping\NamingStrategy`,
`Base\Database\Type\*`) with the same results: two frameworks now need them;
omnibase is to take them from here once it depends on omnibridge/doctrine.

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
