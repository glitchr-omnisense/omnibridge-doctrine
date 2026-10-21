<?php

namespace Omnibridge\Doctrine\Tests\Schema;

use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Omnibridge\Doctrine\DeclarationDriver;
use Omnibridge\Doctrine\MetadataDescription;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\Entity\Thread;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\Harness;
use Omnibridge\Schema\Builder;
use PHPUnit\Framework\TestCase;

/**
 * The description built outside Doctrine (Omnibridge\Schema\Builder, from the
 * declarations alone) is the description of what Doctrine maps
 * (MetadataDescription: its metadata, the tables its SchemaTool creates), for
 * the same classes: the structure is described once, and Doctrine finds it.
 */
final class DescriptionTest extends TestCase
{
    public function testTheDescriptionBuiltWithoutDoctrineIsDoctrines(): void
    {
        $entityManager = Harness::entityManager();
        $doctrine = MetadataDescription::of($entityManager);
        $built = (new Builder(naming: Harness::naming()))->build(Harness::classes());

        self::assertSame(array_keys($doctrine->entities), array_keys($built->entities));
        foreach ($doctrine->entities as $className => $entity) {
            self::assertSame($entity->toArray(), $built->entity($className)->toArray(), $className);
        }
        self::assertSame(array_keys($doctrine->tables), array_keys($built->tables));
        foreach ($doctrine->tables as $name => $table) {
            self::assertSame($table->toArray(), $built->table($name)->toArray(), 'table '.$name);
        }
        self::assertSame($doctrine->toJson(), $built->toJson());

        self::assertSame(['article', 'article_connexes', 'article_keywords', 'notice', 'profile', 'thread', 'threadTag', 'thread_tags', 'user'], array_keys($built->tables));
        self::assertSame(['createdAt', 'updated', 'id', 'slug', 'state', 'class'], array_keys($built->table('thread')->columns));
        self::assertSame('utc_datetime_immutable', $built->table('thread')->column('updated')->type, 'the family\'s own type, by name');
    }

    public function testTheSameThroughTheDeclarationDriverAndTheTablesItCreates(): void
    {
        $entityManager = Harness::entityManager(static fn (MappingDriver $driver) => new DeclarationDriver($driver));
        $built = (new Builder(naming: Harness::naming()))->build(Harness::classes());

        self::assertSame($built->toJson(), MetadataDescription::of($entityManager)->toJson());

        $metadata = array_map($entityManager->getClassMetadata(...), array_filter(Harness::classes(), static fn (string $className) => !$entityManager->getClassMetadata($className)->isMappedSuperclass));
        (new SchemaTool($entityManager))->createSchema(array_values($metadata));
        $tables = $entityManager->getConnection()->createSchemaManager()->listTableNames();
        sort($tables);
        self::assertSame(array_keys($built->tables), $tables, 'the tables created are the description\'s');
        self::assertSame(array_map('strtolower', array_keys($built->table('thread')->columns)), array_keys($entityManager->getConnection()->createSchemaManager()->listTableColumns('thread')), 'the columns created (DBAL lists them lower-cased)');
        self::assertSame('thread', $entityManager->getClassMetadata(Thread::class)->getTableName());
    }
}
