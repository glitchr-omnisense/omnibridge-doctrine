<?php

namespace Omnibridge\Doctrine\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use Omnibridge\Doctrine\EntityManagers;
use App\Entity\Thread;
use PHPUnit\Framework\TestCase;

/** An entity manager built with omnibase's names and types, on SQLite. */
final class EntityManagersTest extends TestCase
{
    private string $zone;

    protected function setUp(): void
    {
        $this->zone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zone);
    }

    public function testItNamesAndKeepsMomentsInUtc(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = EntityManagers::create($connection, [__DIR__.'/Fixtures/Entity'], true);
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Thread::class)]);

        self::assertSame(['id', 'slug', 'publishedAt', 'updatedAt'], array_values(array_map(static fn ($column) => $column->getName(), $connection->createSchemaManager()->listTableColumns('thread'))), 'omnibase\'s table and column names');

        $thread = new Thread();
        $thread->slug = 'le-fuji';
        $thread->publishedAt = new \DateTime('2026-10-05 14:02:00');              // Tokyo
        $thread->updatedAt = new \DateTimeImmutable('2026-10-05 14:02:00');
        $entityManager->persist($thread);
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame(['2026-10-05 05:02:00', '2026-10-05 05:02:00'], array_values($connection->fetchAssociative('SELECT publishedAt, updatedAt FROM thread')), 'written in UTC');
        $read = $entityManager->getRepository(Thread::class)->findOneBy(['slug' => 'le-fuji']);
        self::assertInstanceOf(\DateTimeImmutable::class, $read->publishedAt, 'datetime is read as an immutable moment');
        self::assertSame('2026-10-05 05:02 UTC', $read->publishedAt->format('Y-m-d H:i T'), 'read as UTC');
        self::assertSame('2026-10-05 05:02 UTC', $read->updatedAt->format('Y-m-d H:i T'));
    }

    /** 4.0: the types are the connection's, DBAL's static registry is left alone. */
    public function testAConnectionWithoutTypesOfItsOwnIsGivenTheBridgesProvider(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeQuery('SELECT 1');   // the platform resolved already: it is given the provider too
        $static = (new \Doctrine\DBAL\Configuration())->getTypeProvider();
        EntityManagers::create($connection, [__DIR__.'/Fixtures/Entity']);

        $types = $connection->getConfiguration()->getTypeProvider();
        self::assertNotSame($static, $types);
        self::assertInstanceOf(\Omnibridge\Doctrine\Type\DateTimeType::class, $types->get('datetime'));
        self::assertNotInstanceOf(\Omnibridge\Doctrine\Type\DateTimeType::class, $static->get('datetime'));
        self::assertTrue($connection->getDatabasePlatform()->hasDoctrineTypeMappingFor('datetime'));
        self::assertSame($types, \Closure::bind(fn () => $this->typeProvider, $connection->getDatabasePlatform(), \Doctrine\DBAL\Platforms\AbstractPlatform::class)(), 'the platform resolved before is given it');
    }

    public function testATypeRegistryOfTheHostIsGivenTheBridgesTypes(): void
    {
        $registry = new \Doctrine\DBAL\Types\TypeRegistry();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], (new \Doctrine\DBAL\Configuration())->setTypeProvider($registry));
        EntityManagers::create($connection, [__DIR__.'/Fixtures/Entity']);

        self::assertSame($registry, $connection->getConfiguration()->getTypeProvider());
        self::assertInstanceOf(\Omnibridge\Doctrine\Type\UtcDateTimeImmutableType::class, $registry->get('utc_datetime_immutable'));
    }

    public function testWithoutACacheTheMetadataIsKeptInMemoryNotLookedForOnARedis(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = EntityManagers::create($connection, [__DIR__.'/Fixtures/Entity']);   // not in development: ORMSetup would look for APCu, Memcached, a Redis

        self::assertInstanceOf(\Symfony\Component\Cache\Adapter\ArrayAdapter::class, $entityManager->getConfiguration()->getMetadataCache());
        self::assertSame('thread', $entityManager->getClassMetadata(Thread::class)->getTableName());
    }

    public function testTheConfigurationIsHandedOverBeforeTheEntityManagerExists(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $factory = new \Doctrine\ORM\Repository\DefaultRepositoryFactory();
        $seen = null;
        $entityManager = EntityManagers::create($connection, [__DIR__.'/Fixtures/Entity'], true, configure: function (\Doctrine\ORM\Configuration $configuration) use ($factory, &$seen) {
            $seen = $configuration->getNamingStrategy();
            $configuration->setRepositoryFactory($factory);
        });

        self::assertInstanceOf(\Omnibridge\Doctrine\NamingStrategy::class, $seen, 'after omnibase\'s own settings');
        self::assertTrue($entityManager->getConfiguration()->getUseSchemaEditorApi(), 'doctrine/orm 3.8\'s schema editor API');
        self::assertSame($factory, $entityManager->getConfiguration()->getRepositoryFactory());
    }

    public function testAnEntityWrittenInOmnibasesVocabularyIsMapped(): void
    {
        if (!class_exists(\Base\Mapping\Entity::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = EntityManagers::create($connection, [__DIR__.'/Fixtures/Vocabulary']);

        self::assertInstanceOf(\Omnibridge\Doctrine\DeclarationDriver::class, $entityManager->getConfiguration()->getMetadataDriverImpl());
        self::assertFalse($entityManager->getMetadataFactory()->isTransient(Fixtures\Vocabulary\Badge::class));
        self::assertSame(['id', 'label'], $entityManager->getClassMetadata(Fixtures\Vocabulary\Badge::class)->getFieldNames());
    }
}
