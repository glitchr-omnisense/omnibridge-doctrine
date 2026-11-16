<?php

namespace Omnibridge\Doctrine\Omnibase;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\ManagerEventArgs;
use Omnibridge\Persistence\Lifecycle;

/**
 * Doctrine's lifecycle handed to the neutral listeners (omnisense/omnibridge's Lifecycle\Listeners::TAG): an entity
 * subscriber of an application or a bundle written once on the neutral events - PrePersist ... PostRemove, PostLoad,
 * PreFlush, OnFlush, PostFlush - is called on Doctrine as it is on any other persistence, after omnibase's own
 * behaviours (it listens with the lowest priority a host gives it). A value a PreUpdate listener sets is written
 * back into Doctrine's change set.
 *
 * The host gives it the listeners (Lifecycle\Listeners::chain() of its tagged services) and registers it on the
 * entity manager's events (attach(), or its container's Doctrine listeners: every name of EVENTS).
 */
final class LifecycleRelay
{
    public const EVENTS = [
        Events::prePersist, Events::preUpdate, Events::preRemove,
        Events::postPersist, Events::postUpdate, Events::postRemove, Events::postLoad,
        Events::preFlush, Events::onFlush, Events::postFlush,
    ];

    /** @var \Closure(object): void */
    private readonly \Closure $listeners;

    /** @var array<int, DoctrineUnitOfWork> */
    private array $unitsOfWork = [];

    /** @param iterable<callable(object): void>|\Closure(): iterable<callable(object): void> $listeners */
    public function __construct(iterable|\Closure $listeners)
    {
        $this->listeners = Lifecycle\Listeners::chain($listeners);
    }

    /** @param iterable<callable(object): void>|\Closure(): iterable<callable(object): void> $listeners */
    public static function attach(EntityManagerInterface $entityManager, iterable|\Closure $listeners): self
    {
        $relay = new self($listeners);
        $entityManager->getEventManager()->addEventListener(self::EVENTS, $relay);

        return $relay;
    }

    /** @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    public function prePersist(LifecycleEventArgs $event): void
    {
        ($this->listeners)(new Lifecycle\PrePersist($event->getObject(), $this->unitOfWork($event)));
    }

    /** @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    public function preUpdate(LifecycleEventArgs $event): void
    {
        $unitOfWork = $this->unitOfWork($event);
        $before = $event instanceof PreUpdateEventArgs ? $event->getEntityChangeSet() : $unitOfWork->changes($event->getObject());
        $neutral = new Lifecycle\PreUpdate($event->getObject(), $unitOfWork, $before);
        ($this->listeners)($neutral);
        if ($event instanceof PreUpdateEventArgs) {
            foreach ($neutral->changes() as $field => $change) {
                if (isset($before[$field]) && $before[$field][1] !== $change[1]) {
                    $event->setNewValue($field, $change[1]);
                }
            }
        }
    }

    /** @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    public function preRemove(LifecycleEventArgs $event): void
    {
        ($this->listeners)(new Lifecycle\PreRemove($event->getObject(), $this->unitOfWork($event)));
    }

    /** @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    public function postPersist(LifecycleEventArgs $event): void
    {
        ($this->listeners)(new Lifecycle\PostPersist($event->getObject(), $this->unitOfWork($event)));
    }

    /** @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    public function postUpdate(LifecycleEventArgs $event): void
    {
        ($this->listeners)(new Lifecycle\PostUpdate($event->getObject(), $this->unitOfWork($event)));
    }

    /** @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    public function postRemove(LifecycleEventArgs $event): void
    {
        ($this->listeners)(new Lifecycle\PostRemove($event->getObject(), $this->unitOfWork($event)));
    }

    /** @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    public function postLoad(LifecycleEventArgs $event): void
    {
        ($this->listeners)(new Lifecycle\PostLoad($event->getObject(), $this->unitOfWork($event)));
    }

    /** @param ManagerEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    public function preFlush(ManagerEventArgs $event): void
    {
        ($this->listeners)(new Lifecycle\PreFlush($this->unitOfWork($event)));
    }

    /** @param ManagerEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    public function onFlush(ManagerEventArgs $event): void
    {
        ($this->listeners)(new Lifecycle\OnFlush($this->unitOfWork($event)));
    }

    /** @param ManagerEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    public function postFlush(ManagerEventArgs $event): void
    {
        ($this->listeners)(new Lifecycle\PostFlush($this->unitOfWork($event)));
    }

    /** @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager>|ManagerEventArgs<\Doctrine\Persistence\ObjectManager> $event */
    private function unitOfWork(LifecycleEventArgs|ManagerEventArgs $event): DoctrineUnitOfWork
    {
        $entityManager = $event->getObjectManager();
        if (!$entityManager instanceof \Doctrine\ORM\EntityManagerInterface) {
            throw new \LogicException(sprintf('The lifecycle is relayed from Doctrine ORM\'s entity managers, not from a %s.', $entityManager::class));
        }

        return $this->unitsOfWork[spl_object_id($entityManager)] ??= new DoctrineUnitOfWork($entityManager);
    }
}
