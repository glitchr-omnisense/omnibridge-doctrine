<?php

namespace Omnibridge\Doctrine\Omnibase;

use Base\Database\Mapping\EntityShape;
use Base\Database\Mapping\EntityShapes;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Doctrine\MetadataDescription;

/**
 * The entities' shapes as an entity manager resolved them (plan "omnibase agnostic", step F2c): each
 * class's metadata written in the neutral form by omnibridge/doctrine's MetadataDescription, once per
 * class. Moves to omnibridge/doctrine at step F3.
 */
final class DoctrineShapes implements EntityShapes
{
    /** @var array<string, EntityShape> */
    private array $shapes = [];

    /** @var array<string, true> the classes the entity manager does not map */
    private array $none = [];

    private readonly EntityManagerInterface $entityManager;

    /** @param DoctrineUnitOfWork|EntityManagerInterface $entityManager the unit of work's (Backends builds it from one) */
    public function __construct(DoctrineUnitOfWork|EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager instanceof DoctrineUnitOfWork ? $entityManager->entityManager() : $entityManager;
    }

    public function has(string|object $class): bool
    {
        $name = \is_object($class) ? $class::class : $class;
        if (isset($this->shapes[$name])) {
            return true;
        }
        if (isset($this->none[$name])) {
            return false;
        }

        try {
            $metadata = $this->entityManager->getClassMetadata($name);
        } catch (\Throwable) {
            return !$this->none[$name] = true;
        }

        return !$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass;
    }

    /**
     * The mapped entities' classes, those has() counts: every class the entity manager's metadata factory loads but
     * the mapped superclasses and the embeddables (omnisense/omnibase's plan "omnibase agnostic", step B1d: the back
     * office's pickable pages listed them from Doctrine's metadata itself).
     *
     * @return list<string>
     */
    public function classes(): array
    {
        $classes = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (!$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass) {
                $classes[] = $metadata->getName();
            }
        }

        return $classes;
    }

    public function shape(string|object $class): EntityShape
    {
        $name = \is_object($class) ? $class::class : $class;
        if (isset($this->shapes[$name])) {
            return $this->shapes[$name];
        }
        try {
            $metadata = $this->entityManager->getClassMetadata($name);
        } catch (\Throwable $e) {
            throw new \OutOfBoundsException(sprintf('No class %s in the entity manager.', $name), 0, $e);
        }

        return $this->shapes[$name] = $this->shapes[$metadata->getName()] ??= new EntityShape(MetadataDescription::entity($metadata));
    }
}
