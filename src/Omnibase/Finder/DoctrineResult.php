<?php

namespace Omnibridge\Doctrine\Omnibase\Finder;

use Base\Database\Repository\Finder\Result;
use Doctrine\ORM\Query;
use Omnibridge\Doctrine\Omnibase\DatabaseErrors;
use Omnibridge\Doctrine\Omnibase\QueryPages;

/**
 * A finder's result on Doctrine: the DQL query the compiler made, run when its result is asked. As Pages, it is
 * counted and sliced by QueryPages (omnibase's walkers).
 *
 * @internal
 */
final class DoctrineResult implements Result
{
    private ?QueryPages $pages = null;

    public function __construct(private readonly Query $query)
    {
    }

    /** The DQL query, for this package's own code (a statements class, the pagination): never a site's. */
    public function query(): Query
    {
        return $this->query;
    }

    public function getResult(): array
    {
        return DatabaseErrors::reading(fn () => $this->query->getResult());
    }

    public function getOneOrNullResult(): mixed
    {
        return DatabaseErrors::reading(fn () => $this->query->getOneOrNullResult());
    }

    public function getSingleScalarResult(): mixed
    {
        return DatabaseErrors::reading(fn () => $this->query->getSingleScalarResult());
    }

    public function getArrayResult(): array
    {
        return DatabaseErrors::reading(fn () => $this->query->getArrayResult());
    }

    public function toIterable(): iterable
    {
        return DatabaseErrors::reading(fn () => $this->query->toIterable());
    }

    public function count(): int
    {
        return DatabaseErrors::reading(fn (): int => ($this->pages ??= new QueryPages($this->query))->count());
    }

    public function slice(int $offset, int $limit): array
    {
        return DatabaseErrors::reading(fn (): array => ($this->pages ??= new QueryPages($this->query))->slice($offset, $limit));
    }

    /** @return \ArrayIterator<int, mixed> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->getResult());
    }

    /** The compiled DQL, its parameters and bounds: what the core's finder tests compare. @internal */
    public function getDQL(): ?string
    {
        return $this->query->getDQL();
    }

    /** @return string|list<string> */
    public function getSQL(): string|array
    {
        return $this->query->getSQL();
    }

    /** @return \Doctrine\Common\Collections\ArrayCollection<int, \Doctrine\ORM\Query\Parameter> */
    public function getParameters(): \Doctrine\Common\Collections\ArrayCollection
    {
        return $this->query->getParameters();
    }

    public function getFirstResult(): int
    {
        return $this->query->getFirstResult();
    }

    public function getMaxResults(): ?int
    {
        return $this->query->getMaxResults();
    }

    public function getParameter(int|string $key): ?\Doctrine\ORM\Query\Parameter
    {
        return $this->query->getParameter($key);
    }
}
