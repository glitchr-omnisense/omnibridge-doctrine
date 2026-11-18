<?php

namespace Omnibridge\Doctrine\Omnibase\Behaviour;

use Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\SlugifyBackend;

/** #[Slugify]'s part on Doctrine (plan "omnibase agnostic", step F2b; moves to omnibridge/doctrine at F3). */
final class DoctrineSlugifyBackend implements SlugifyBackend
{
    /** @var array<string, bool> table.column => unique alone, asked once per process */
    private static array $uniqueAlone = [];

    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function pendingWrites(): array
    {
        $unitOfWork = $this->unitOfWork->entityManager()->getUnitOfWork();

        $entities = [];
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $entities[] = $entity;
        }
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $entities[] = $entity;
        }

        return $entities;
    }

    public function uniqueAlone(string $class, string $property): bool
    {
        try {
            $entityManager = $this->unitOfWork->entityManager();
            $classMetadata = $entityManager->getClassMetadata($class);
            $table = $classMetadata->getTableName();
            $column = $classMetadata->getColumnName($property);
            $key = $table.'.'.$column;
            if (array_key_exists($key, self::$uniqueAlone)) {
                return self::$uniqueAlone[$key];
            }

            self::$uniqueAlone[$key] = false;
            $indexes = $entityManager->getConnection()->createSchemaManager()->listTableIndexes($table);
            // By the indexed columns and the index type where DBAL has them (isUnique(), isPrimary() and
            // getUnquotedColumns() are deprecated since DBAL 4.5): the primary key, which DBAL still lists among the
            // indexes, is the unique index on the identifier's columns, never the slug's alone.
            // @phpstan-ignore function.alreadyNarrowedType (DBAL 4.2, which composer.json still allows, has no indexed columns)
            if (method_exists(\Doctrine\DBAL\Schema\Index::class, 'getIndexedColumns')) {
                $identifier = array_map('strtolower', $classMetadata->getIdentifierColumnNames());
                foreach ($indexes as $index) {
                    $columns = array_map(static fn ($indexed): string => strtolower($indexed->getColumnName()->getIdentifier()->getValue()), $index->getIndexedColumns());
                    if (\Doctrine\DBAL\Schema\Index\IndexType::UNIQUE === $index->getType() && $columns !== $identifier && $columns === [strtolower($column)]) {
                        self::$uniqueAlone[$key] = true;
                    }
                }
            } else {
                foreach ($indexes as $index) {
                    if ($index->isUnique() && !$index->isPrimary() && array_map('strtolower', $index->getUnquotedColumns()) === [strtolower($column)]) {
                        self::$uniqueAlone[$key] = true;
                    }
                }
            }

            return self::$uniqueAlone[$key];
        } catch (\Throwable) {
            return false;
        }
    }
}
