<?php

namespace Omnibridge\Doctrine\Omnibase\Behaviour;

use Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\Backends;
use Omnibridge\Doctrine\Omnibase\DoctrineShapes;
use Base\Database\Attribute\Backend\CacheBackend;
use Base\Database\Attribute\Backend\OrderColumnBackend;
use Base\Database\Attribute\Backend\SlugifyBackend;
use Base\Database\Attribute\Backend\VaultBackend;
use Base\Database\Mapping\EntityShapes;
use Base\Database\Repository\EntityRepositories;
use Omnibridge\Doctrine\Omnibase\DoctrineFinders;
use Omnibridge\Doctrine\Omnibase\DoctrineBackend;
use Base\Database\Backend\BulkDeletes;
use Base\Database\Backend\ColumnTypes;
use Base\Database\Backend\ConditionalUpdates;
use Base\Database\Backend\EntityCache;
use Base\Database\Backend\RowLocks;
use Base\Database\Repository\Repositories;
use Omnibridge\Persistence\Backend;

/**
 * The behaviours' parts on Doctrine, the entities' shapes (step F2c), the repositories and what the
 * core's services ask beyond the unit of work's contract (step F2d), registered for
 * DoctrineUnitOfWork (plan "omnibase agnostic", step F2b): AttributeSubscriber, the Doctrine relay that
 * dispatches the neutral lifecycle hooks, registers them when it is made, under
 * Symfony and in Omnibridge\Doctrine\Omnibase\Mapping alike. Moves to omnibridge/doctrine
 * at step F3, with the three implementations.
 */
final class DoctrineBehaviours
{
    public const BACKENDS = [
        VaultBackend::class => DoctrineVaultBackend::class,
        SlugifyBackend::class => DoctrineSlugifyBackend::class,
        CacheBackend::class => DoctrineCacheBackend::class,
        OrderColumnBackend::class => DoctrineOrderColumnBackend::class,
        EntityShapes::class => DoctrineShapes::class,
        // Step F2d: what the core's services ask of a unit of work beyond its contract (and the repositories, below).
        RowLocks::class => DoctrineRowLocks::class,
        ConditionalUpdates::class => DoctrineConditionalUpdates::class,
        EntityCache::class => DoctrineEntityCache::class,
        // Step F2e: the scopes (trash, vault) as Doctrine's filters, and deletes by criteria.
        Backend::class => DoctrineBackend::class,
        BulkDeletes::class => DoctrineBulkDeletes::class,
        // Step F5c, item 3: which column types hold an array (EntityHydrator's defaults).
        ColumnTypes::class => DoctrineColumnTypes::class,
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        self::$registered = true;
        foreach (self::BACKENDS as $behaviour => $backend) {
            Backends::register(DoctrineUnitOfWork::class, $behaviour, $backend);
        }
        // The repositories over the unit of work's entity manager: the host's registry where it has one for that entity
        // manager (its container's: the application's repositories as its services, their own dependencies given), else
        // the core's registry on Doctrine's finders.
        Backends::register(DoctrineUnitOfWork::class, Repositories::class, static function (\Omnibridge\Persistence\UnitOfWork $unitOfWork): Repositories {
            if (!$unitOfWork instanceof DoctrineUnitOfWork) {
                throw new \LogicException(sprintf('Doctrine\'s repositories are built on a %s, not on a %s.', DoctrineUnitOfWork::class, $unitOfWork::class));
            }
            $host = null !== self::$host ? (self::$host)($unitOfWork->entityManager()) : null;

            return $host instanceof Repositories ? $host : new EntityRepositories(DoctrineFinders::over($unitOfWork->entityManager()));
        });
    }

    /** @var (\Closure(\Doctrine\ORM\EntityManagerInterface): ?Repositories)|null */
    private static ?\Closure $host = null;

    /**
     * The host's registry of repositories for an entity manager of its own (null for another one): what
     * UnitOfWorks::repositories() answers on that entity manager's unit of work - a fixtures command's, a lifecycle
     * listener's - so that a repository taking a service of the application's (a cache pool) is that service.
     *
     * @internal the hosts' (omnibridge/symfony's BaseBundle, omnibridge/laravel's provider)
     *
     * @param (\Closure(\Doctrine\ORM\EntityManagerInterface): ?Repositories)|null $repositories
     */
    public static function host(?\Closure $repositories): void
    {
        self::$host = $repositories;
        // The registries already made on the former answer are forgotten (Backends keeps one per unit of work).
        self::register();
    }

    /** Registers them unless done: a DoctrineUnitOfWork made before the relay (a service's, at the top of a request) finds them. */
    public static function ensureRegistered(): void
    {
        if (!self::$registered) {
            self::register();
        }
    }
}
