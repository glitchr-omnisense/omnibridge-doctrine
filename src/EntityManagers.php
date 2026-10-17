<?php

namespace Omnibridge\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Psr\Cache\CacheItemPoolInterface;

/**
 * An entity manager over an omnibase site's database, for a framework that
 * has none of its own: attribute mapping of the given folders, omnibase's
 * naming strategy and types, PHP's native lazy objects for proxies.
 */
final class EntityManagers
{
    /** @param list<string> $paths the folders of the entities */
    public static function create(Connection $connection, array $paths, bool $development = false, ?CacheItemPoolInterface $cache = null): EntityManager
    {
        Types::register();

        $configuration = ORMSetup::createAttributeMetadataConfig($paths, $development, null, $cache);
        $configuration->setNamingStrategy(new NamingStrategy());
        if (method_exists($configuration, 'enableNativeLazyObjects') && \PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        return new EntityManager($connection, $configuration);
    }
}
