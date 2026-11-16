<?php

namespace Omnibridge\Doctrine\Omnibase;

use Doctrine\DBAL\ParameterType;
use Base\Database\Entity\EntityHydrator;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Mapping\EntityShapes;
use Base\Database\Repository\Finder;
use Base\Database\Repository\Finders;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Omnibridge\Doctrine\Omnibase\Finder\DoctrineFinder;
use Omnibridge\Doctrine\Omnibase\Finder\DqlCompiler;
use Omnibridge\Persistence\UnitOfWork;

/**
 * omnibase's Finders on Doctrine: what the core's repositories (Base\Database\Repository\ServiceEntityRepository
 * and every class over it) are built from. The engine of a class is the DQL compiler over the entity manager of
 * that class and a plain Doctrine EntityRepository; sql() runs on its DBAL connection; the unit of work is the
 * default entity manager's. The host binds it as Base\Database\Repository\Finders.
 */
final class DoctrineFinders implements Finders
{
    /** @var array<string, DoctrineFinder> */
    private array $finders = [];

    /** @var array<string, EntityRepository<object>> */
    private array $plain = [];

    private ?ClassMetadataManipulator $manipulator = null;


    private ?DoctrineUnitOfWork $unitOfWork = null;

    public function __construct(private readonly ManagerRegistry $registry)
    {
    }

    /** Over one entity manager (an entity manager built by hand, a test). */
    public static function over(EntityManagerInterface $entityManager): self
    {
        return new self(new Registry($entityManager));
    }

    public function of(string $class): Finder
    {
        return $this->finders[$class] ??= $this->make($class);
    }

    public function shapes(): EntityShapes
    {
        return $this->manipulator()->getShapes();
    }

    public function unitOfWork(): UnitOfWork
    {
        return $this->unitOfWork ??= new DoctrineUnitOfWork($this->defaultManager());
    }

    public function backend(): string
    {
        return 'doctrine';
    }


    /** The entity manager of a class. */
    public function entityManager(string $class): EntityManagerInterface
    {
        $entityManager = $this->registry->getManagerForClass($class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('Could not find the entity manager for class "%s". Check your Doctrine configuration to make sure it is configured to load this entity’s metadata.', $class));
        }

        return $entityManager;
    }

    /**
     * Doctrine's plain repository of a class, never an application's: what the compiler builds its queries on, and
     * what a statements class asks for createQueryBuilder().
     *
     * @return EntityRepository<object>
     */
    public function plainRepository(string $class): EntityRepository
    {
        $entityManager = $this->entityManager($class);
        $metadata = $entityManager->getClassMetadata($class);

        return $this->plain[$metadata->getName()] ??= new EntityRepository($entityManager, $metadata);
    }

    private function make(string $class): DoctrineFinder
    {
        $entityManager = $this->entityManager($class);
        $manipulator = $this->manipulator();
        $hydrator = new EntityHydrator(new DoctrineUnitOfWork($entityManager), $manipulator->getShapes());

        return new DoctrineFinder($entityManager, new DqlCompiler($this->plainRepository($class), $entityManager, $manipulator, $hydrator));
    }

    private function manipulator(): ClassMetadataManipulator
    {
        return $this->manipulator ??= new ClassMetadataManipulator($this->registry, $this->defaultManager());
    }

    private function defaultManager(): EntityManagerInterface
    {
        $entityManager = $this->registry->getManager();
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('Doctrine\'s default manager is no entity manager.');
        }

        return $entityManager;
    }
    public function sql(string $sql, array $parameters = [], array $selections = []): array|int
    {
        $connection = $this->defaultManager()->getConnection();
        $values = [];
        $types = [];
        $pieces = preg_split('/\{selection:(\d+)\}/', $sql, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [$sql];
        $written = '';
        $given = $parameters;
        foreach ($pieces as $index => $piece) {
            if (1 === $index % 2) {
                [$class, $criteria] = $selections[(int) $piece] ?? throw new \LogicException(sprintf('No selection %s for %s.', $piece, $sql));
                $finder = $this->of($class);
                \assert($finder instanceof DoctrineFinder);
                [$subSql, $subValues, $subTypes] = self::compiled($finder->compiler()->identifiers($criteria));
                $written .= $subSql;
                array_push($values, ...$subValues);
                array_push($types, ...$subTypes);
                continue;
            }
            $written .= $piece;
            for ($n = substr_count($piece, '?'); $n > 0; --$n) {
                $values[] = array_shift($given);
                $types[] = ParameterType::STRING;
            }
        }
        foreach ($values as $position => $value) {
            if (\is_int($value) && ParameterType::STRING === $types[$position]) {
                $types[$position] = ParameterType::INTEGER;
            } elseif (null === $value && ParameterType::STRING === $types[$position]) {
                $types[$position] = ParameterType::NULL;
            }
        }

        return DatabaseErrors::reading(static fn (): array|int => 1 === preg_match('/^\s*(SELECT|WITH)\b/i', $written)
            ? $connection->fetchAllAssociative($written, $values, $types)
            : (int) $connection->executeStatement($written, $values, $types));
    }

    public function platform(): string
    {
        $platform = $this->defaultManager()->getConnection()->getDatabasePlatform();

        return match (true) {
            $platform instanceof \Doctrine\DBAL\Platforms\SQLitePlatform => 'sqlite',
            $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform => 'pgsql',
            default => 'mysql',
        };
    }

    /**
     * A DQL query as SQL, its parameters in order with their types (Doctrine's own parse; its filters are in the SQL).
     *
     * @return array{0: string, 1: list<mixed>, 2: list<mixed>}
     */
    private static function compiled(\Doctrine\ORM\Query $query): array
    {
        $sql = $query->getSQL();
        \assert(\is_string($sql));
        [$values, $types] = (\Closure::bind(fn (): array => $this->processParameterMappings($this->parse()->getParameterMappings()), $query, \Doctrine\ORM\Query::class))();

        return [$sql, $values, array_values($types)];
    }
}
