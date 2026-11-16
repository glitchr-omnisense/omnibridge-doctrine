<?php

namespace Omnibridge\Doctrine\Omnibase;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Repository\RepositoryFactory as RepositoryFactoryInterface;

/**
 * What Doctrine's own getRepository() answers once omnibase is installed: Doctrine's plain EntityRepository of the
 * class, whatever repositoryClass its mapping names. omnibase's repositories (Base\Repository\*, a bundle's,
 * App\Repository\*) are no Doctrine repositories: they are built from Base\Database\Repository\Finders and served by
 * Base\Database\Repository\Repositories::of(); Doctrine's default factory would try to build them from the entity
 * manager. Set on the configuration by the host (Mapping::configure(), the Symfony bridge's Doctrine configuration).
 */
final class RepositoryFactory implements RepositoryFactoryInterface
{
    /** @var array<string, EntityRepository<object>> */
    private array $repositories = [];

    /**
     * @template T of object
     *
     * @param class-string<T> $entityName
     *
     * @return EntityRepository<T>
     */
    public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
    {
        $metadata = $entityManager->getClassMetadata($entityName);
        /** @var EntityRepository<T> $repository the one of that class (each class its own) */
        $repository = $this->repositories[$metadata->getName().spl_object_id($entityManager)] ??= new EntityRepository($entityManager, $metadata);

        return $repository;
    }
}
