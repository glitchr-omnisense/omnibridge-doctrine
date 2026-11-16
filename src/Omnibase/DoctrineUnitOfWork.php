<?php

namespace Omnibridge\Doctrine\Omnibase;

use Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineBehaviours;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork as DoctrineUnitOfWorkState;
use Omnibridge\Persistence\Schedule;
use Omnibridge\Persistence\Snapshots;
use Omnibridge\Persistence\UnitOfWork;

/**
 * omnisense/omnibridge's UnitOfWork over a Doctrine entity manager (plan "omnibase agnostic", step
 * F2): what the core's code asks where it spoke to the entity manager, and what the lifecycle events
 * the Doctrine relay dispatches carry. Moves to omnibridge/doctrine at step F3.
 */
final class DoctrineUnitOfWork implements UnitOfWork, Snapshots, Schedule
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        // Its backends (repositories, shapes, the behaviours' parts) are there for any unit of work made.
        DoctrineBehaviours::ensureRegistered();
    }

    public function entityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->entityManager->remove($entity);
    }

    public function flush(): void
    {
        DatabaseErrors::writing(fn () => $this->entityManager->flush());
    }

    public function clear(): void
    {
        $this->entityManager->clear();
    }

    public function isOpen(): bool
    {
        return $this->entityManager->isOpen();
    }

    public function refresh(object $entity): void
    {
        $this->entityManager->refresh($entity);
    }

    public function contains(object $entity): bool
    {
        return $this->entityManager->contains($entity);
    }

    public function reference(string $class, mixed $id): object
    {
        return $this->entityManager->getReference($class, $id);
    }

    public function transactional(callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(fn () => $work($this));
    }

    public function isNew(object $entity): bool
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();

        // Persisted and not flushed yet has no row either.
        return $unitOfWork->isScheduledForInsert($entity)
            || DoctrineUnitOfWorkState::STATE_NEW === $unitOfWork->getEntityState($entity, DoctrineUnitOfWorkState::STATE_NEW);
    }

    public function identifier(object $entity): mixed
    {
        $values = $this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity);

        return match (\count($values)) {
            0 => null,
            1 => reset($values),
            default => $values,
        };
    }

    public function className(object $entity): string
    {
        return $this->entityManager->getClassMetadata($entity::class)->getName();
    }

    /** Doctrine's original entity data (omnisense/omnibridge's Snapshots): the fields and to-one associations as read or last flushed. */
    public function original(object $entity): array
    {
        return $this->entityManager->getUnitOfWork()->getOriginalEntityData($entity);
    }

    public function changes(object $entity): array
    {
        return $this->entityManager->getUnitOfWork()->getEntityChangeSet($entity);
    }

    /**
     * Doctrine's recomputeSingleEntityChangeSet(): for an entity a listener changed during a flush. An
     * entity a listener persisted during the flush (OnFlush) has no change set to compute again: its first
     * one is computed (computeChangeSet(), what Doctrine asks of an onFlush listener for a new entity),
     * where recomputeSingleEntityChangeSet() threw ("before computeChangeSet").
     */
    public function recompute(object $entity): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $metadata = $this->entityManager->getClassMetadata($entity::class);

        if ($unitOfWork->isScheduledForInsert($entity) && [] === $unitOfWork->getOriginalEntityData($entity)) {
            $unitOfWork->computeChangeSet($metadata, $entity);

            return;
        }

        $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
    }

    public function scheduledInsertions(): array
    {
        return array_values($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions());
    }

    public function scheduledUpdates(): array
    {
        return array_values($this->entityManager->getUnitOfWork()->getScheduledEntityUpdates());
    }

    public function scheduledDeletions(): array
    {
        return array_values($this->entityManager->getUnitOfWork()->getScheduledEntityDeletions());
    }

    public function scheduledCollectionUpdates(): array
    {
        $updates = [];
        foreach ($this->entityManager->getUnitOfWork()->getScheduledCollectionUpdates() as $collection) {
            $updates[] = [
                'entity' => $collection->getOwner(),
                'field' => (string) ($collection->getMapping()->fieldName ?? ''),
                'added' => array_values($collection->getInsertDiff()),
                'removed' => array_values($collection->getDeleteDiff()),
            ];
        }

        return $updates;
    }
}
