<?php

namespace Omnibridge\Doctrine;

use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * An entity manager over an omnibase site's database, for a framework that
 * has none of its own: attribute mapping of the given folders (in Doctrine's
 * or omnibase's vocabulary, through the DeclarationDriver), omnibase's
 * naming strategy and types, PHP's native lazy objects for proxies.
 *
 * The types are the connection's (4.0): a connection configured without a type
 * provider of its own (DBAL's static registry, deprecated in DBAL 4.6, gone in
 * 5) is given one, Types::provider(); a TypeRegistry the host gave it is given
 * the bridge's types; any other provider is the host's, left as it is.
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
        self::types($connection);

        $configuration = ORMSetup::createAttributeMetadataConfig($paths, $development, null, $cache ?? new ArrayAdapter());
        $configuration->setNamingStrategy(new NamingStrategy());
        if (method_exists($configuration, 'enableNativeLazyObjects') && \PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }
        if (null !== $configure) {
            $configure($configuration);
        }
        // The structure read from the declarations (DeclarationDriver): an entity written in omnibase's vocabulary
        // (#[Base\Mapping\Entity]) is one, which Doctrine's attribute driver alone would call transient.
        $driver = $configuration->getMetadataDriverImpl();
        if (null !== $driver && !$driver instanceof DeclarationDriver && !$driver instanceof Omnibase\MappingDriver) {
            $configuration->setMetadataDriverImpl(new DeclarationDriver($driver));
        }

        return new EntityManager($connection, $configuration);
    }

    private static function types(Connection $connection): void
    {
        $configuration = $connection->getConfiguration();
        $provider = $configuration->getTypeProvider();
        // A configuration's provider defaults to DBAL's static registry (no deprecation read so; DBAL 5 has none).
        if ($provider === (new DbalConfiguration())->getTypeProvider()) {
            $configuration->setTypeProvider($provider = Types::provider());
            // A platform already resolved (the connection used, or its server version given) took the provider then.
            $parameters = $connection->getParams();
            if ($connection->isConnected() || isset($parameters['serverVersion']) || isset($parameters['primary']['serverVersion'])) {
                $connection->getDatabasePlatform()->setTypeProvider($provider);
            }
        } elseif ($provider instanceof TypeRegistry) {
            Types::provider($provider);
        }
    }
}
