<?php

namespace Omnibridge\Doctrine\Omnibase\Finder;

use Base\Database\Repository\Finder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Omnibridge\Doctrine\Omnibase\DatabaseErrors;

/**
 * The engine of one entity class on Doctrine: the finder language compiled to DQL (DqlCompiler) and run, a
 * finder's query handed back as a Result.
 *
 * @internal
 */
final class DoctrineFinder implements Finder
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly DqlCompiler $compiler)
    {
    }

    public function className(): string
    {
        return $this->compiler->getClassName();
    }

    public function compiler(): DqlCompiler
    {
        return $this->compiler;
    }

    public function run(string $method, array $arguments): mixed
    {
        $result = DatabaseErrors::reading(fn (): mixed => $this->compiler->parse($method, $arguments));

        return $result instanceof Query ? new DoctrineResult($result) : ($result instanceof AbstractQuery ? $result->getResult() : $result);
    }

    public function find(mixed $id): ?object
    {
        $class = $this->className();
        \assert(class_exists($class));

        return DatabaseErrors::reading(fn (): ?object => $this->entityManager->find($class, $id));
    }

    public function clear(): void
    {
        $this->entityManager->clear();
    }
}
