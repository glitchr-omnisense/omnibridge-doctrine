<?php

namespace Omnibridge\Doctrine\Omnibase;

use Psr\Container\ContainerInterface;

/**
 * A host's services by their types, from a closure (Mapping::register()'s option `services`), as the PSR-11
 * locator the core's EntityRepositories asks a repository's own services from.
 *
 * @internal
 */
final class ServiceLocator implements ContainerInterface
{
    /** @var array<string, object|null> */
    private array $found = [];

    /** @param \Closure(class-string): ?object $services */
    public function __construct(private readonly \Closure $services)
    {
    }

    public function has(string $id): bool
    {
        return null !== $this->find($id);
    }

    public function get(string $id): object
    {
        return $this->find($id) ?? throw new \OutOfBoundsException(sprintf('The host gives no service of type %s.', $id));
    }

    private function find(string $id): ?object
    {
        return \array_key_exists($id, $this->found) ? $this->found[$id] : $this->found[$id] = ($this->services)($id);
    }
}
