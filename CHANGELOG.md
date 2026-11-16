# Changelog

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
