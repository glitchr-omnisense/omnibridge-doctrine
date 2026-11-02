<?php

namespace Omnibridge\Doctrine\Omnibase;

use Base\Service\Locator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;

/**
 * Doctrine's services as omnibase's host code reached them through BaseTrait
 * before it named contracts only (plan "omnibase agnostic", step F2f): the
 * registry, an entity's manager and repository, the default entity manager and
 * the metadata manipulator, resolved through the `base.runtime` locator
 * BaseBundle::boot() seeds and kept on Base\Service\Locator. Each answers null
 * where the host has no such service (a host without Doctrine, Laravel).
 *
 * Base\Bridge\Symfony\FrameworkLocator's Doctrine half, here since step F3;
 * FrameworkLocator keeps its methods, which call these, and its Symfony half
 * until F5c.
 */
final class DoctrineLocator
{
    public static function doctrine(): ?ManagerRegistry
    {
        return Locator::runtimeGet('doctrine', 'doctrine');
    }

    public static function objectManager(object|string $entity): ?ObjectManager
    {
        return self::doctrine()?->getManagerForClass(is_object($entity) ? get_class($entity) : $entity);
    }

    /** The default entity manager; a closed one is null, or recreated when $reopen. */
    public static function entityManager(bool $reopen = false): ?EntityManagerInterface
    {
        $doctrine = self::doctrine();
        if (!$doctrine) {
            return null;
        }

        $entityManager = $doctrine->getManager($doctrine->getDefaultManagerName());
        if (!$entityManager instanceof EntityManagerInterface) {
            return null;
        }
        if (!$entityManager->isOpen()) {
            if (!$reopen) {
                return null;
            }

            /** @var \Doctrine\ORM\EntityManager $entityManager */
            $entityManager = $entityManager->create($entityManager->getConnection(), $entityManager->getConfiguration());
        }

        return $entityManager;
    }

    public static function repository(object|string $entity): ?ObjectRepository
    {
        $class = is_object($entity) ? get_class($entity) : $entity;

        return self::objectManager($class)?->getRepository($class);
    }

    public static function classMetadataManipulator(): ?ClassMetadataManipulator
    {
        return Locator::runtimeGet('classMetadataManipulator', 'base.database.metadata_manipulator');
    }
}
