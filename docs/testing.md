---
title: Testing
order: 60
---

# Testing

## Testing an application's mapping

- The two descriptions: `MetadataDescription::of($entityManager)` against `Builder::build()` over the
  same classes, compared as JSON ([Descriptions](descriptions.md#comparing-descriptions)).
- The database: `MySqlSchema::read($pdo)->differences(MetadataDescription::of($entityManager))` must be
  `[]` after the migrations.
- An entity manager on SQLite in memory (`DriverManager::getConnection(['driver' => 'pdo_sqlite',
  'memory' => true])`) with `EntityManagers::create()` and `SchemaTool::createSchema()`, as the
  [Installation](installation.md) page does.

## The package's suite

No Docker harness of its own: the suite runs in lesringostars-laravel's image, which has PHP 8.4,
pdo_sqlite, intl and mbstring, with the checkout's `vendor/` installed:

```sh
docker run --rm -v "$PWD":/app -w /app lesringostars-laravel-app vendor/bin/phpunit
```

| Test class | What it checks |
|---|---|
| `Schema\VocabularyTest` | every attribute of the core's `Vocabulary` is doctrine/orm's with the same parameters, in order, the same defaults and repeatability; every mapping attribute of doctrine/orm is in the vocabulary; the lifecycle callbacks are Doctrine's events |
| `Schema\DeclarationDriverTest` | the metadata are the attribute driver's, class by class; the same from declarations kept as data (through JSON); a class the declarations do not hold is left to the wrapped driver; a class that is no entity is refused as Doctrine refuses it |
| `Schema\DescriptionTest` | the description built without Doctrine is Doctrine's; the same through the declaration driver, and the tables SchemaTool creates (SQLite) |
| `NamingStrategyTest` | every table of lesringostars; every join table and its columns; the rules on their own |
| `TypesTest` | a date written and read in UTC; an unreadable value is a `ConversionException` as in DBAL 4; the UTC types may be extended |
| `EntityManagersTest` | it names and keeps moments in UTC; without a cache the metadata are kept in memory, not looked for on a Redis; the configuration is handed over before the entity manager exists |

The CI (GitHub Actions, glitchr-studio/ci's package workflow) runs PHPUnit and PHPStan at the highest
level against `phpstan-baseline.neon`.
