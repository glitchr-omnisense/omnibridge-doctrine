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
        self::assertSame('2026-10-05 05:02 UTC', $read->publishedAt->format('Y-m-d H:i T'), 'read as UTC');
        self::assertSame('2026-10-05 05:02 UTC', $read->updatedAt->format('Y-m-d H:i T'));
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
        self::assertSame($factory, $entityManager->getConfiguration()->getRepositoryFactory());
    }
}
