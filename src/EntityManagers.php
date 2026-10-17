<?php

namespace Omnibridge\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * An entity manager over an omnibase site's database, for a framework that
 * has none of its own: attribute mapping of the given folders, omnibase's
 * naming strategy and types, PHP's native lazy objects for proxies.
 *
 * Without a cache, the metadata is kept in memory for the request (an
 * ArrayAdapter): ORMSetup's own default, outside development, looks for APCu,
 * Memcached, then a Redis on 127.0.0.1. $configure receives the configuration
 * before the entity manager is built - a repository factory, filters, DQL
 * functions: what can no longer change once it exists.
 */
final class EntityManagers
{
    /**
     * @param list<string>                        $paths     the folders of the entities
     * @param (\Closure(Configuration): void)|null $configure
     */
    public static function create(Connection $connection, array $paths, bool $development = false, ?CacheItemPoolInterface $cache = null, ?\Closure $configure = null): EntityManager
    {
        Types::register();

        $configuration = ORMSetup::createAttributeMetadataConfig($paths, $development, null, $cache ?? new ArrayAdapter());
        $configuration->setNamingStrategy(new NamingStrategy());
        if (method_exists($configuration, 'enableNativeLazyObjects') && \PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }
        if (null !== $configure) {
            $configure($configuration);
        }

        return new EntityManager($connection, $configuration);
    }
}
