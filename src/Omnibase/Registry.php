<?php

namespace Omnibridge\Doctrine\Omnibase;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\Proxy;

/**
 * doctrine/persistence's ManagerRegistry over one entity manager and its
 * connection, both named "default": what omnibase's repositories
 * (Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository) and the
 * ClassMetadataManipulator are built from, where DoctrineBundle's `doctrine`
 * service is not there (Omnibridge\Doctrine\Omnibase\Mapping).
 */
final class Registry extends AbstractManagerRegistry
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct('ORM', ['default' => 'connection'], ['default' => 'entity_manager'], 'default', 'default', Proxy::class);
    }

    protected function getService(string $name): object
    {
        return $name === 'connection' ? $this->entityManager->getConnection() : $this->entityManager;
    }

    protected function resetService(string $name): void
    {
    }
}
