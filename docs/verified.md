---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-27 in `lesringostars-laravel-app` (PHP 8.4.27), the checkout's own `vendor/`
(doctrine/orm 3.7.5, dbal 4.5.0), no network: 18 tests, 464 assertions, green
([Testing](testing.md#the-packages-own-suite)). The [Installation](installation.md) page's script was
run in the same image that day and its output pasted: the metadata through the declaration driver, the
two descriptions equal, a moment stored in UTC.

## Verified elsewhere, as the commits and the README state

- omnisense/omnibase's harness: the builder's description equal to Doctrine's on every class it maps
  (202 classes when the Schema was written).
- lesringostars: `MetadataSnapshot` (the declaration driver's metadata equal to the attribute
  driver's) on both hosts, the description compared with its MySQL database (MySQL 8.4.11), the naming
  checked on its tables (`tests/lesringostars-tables.json`).

## What is not verified

- doctrine/orm and dbal versions other than 3.7.5 and 4.5.0 (composer.json admits ^3.4 and ^4.2).
- Databases other than SQLite (the suite) and MySQL (lesringostars): no PostgreSQL run.
- XML or PHP mappings through the wrapped driver: the fallback is tested with a class the declarations
  do not hold, not with another mapping format.
