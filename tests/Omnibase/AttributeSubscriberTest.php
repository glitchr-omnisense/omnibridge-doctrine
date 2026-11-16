<?php

namespace Omnibridge\Doctrine\Tests\Omnibase;

use App\Entity\Thread;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\Omnibase\Mapping;
use PHPUnit\Framework\TestCase;

/**
 * omnibase's attribute relay on Doctrine reads an entity's identifier through Doctrine's metadata: an entity with a
 * public $id and no getId() (any application's) is written, changed and removed like any other.
 */
final class AttributeSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Base\Entity\Thread::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
    }

    public function testAnEntityWithoutAGetterIsFlushed(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = EntityManagers::create($connection, [\dirname(__DIR__).'/Fixtures/Entity'], true, null, Mapping::configure(...));
        Mapping::register($entityManager, ['project_dir' => sys_get_temp_dir(), 'cache_dir' => sys_get_temp_dir().'/omnibridge-doctrine-attribute-test']);
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Thread::class)]);
        self::assertFalse(method_exists(Thread::class, 'getId'));

        $thread = new Thread();
        $thread->slug = 'first';
        $entityManager->persist($thread);
        $entityManager->flush();
        self::assertNotNull($thread->id);

        $thread->slug = 'second';
        $entityManager->flush();
        $entityManager->remove($thread);
        $entityManager->flush();

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$entityManager->getClassMetadata(Thread::class)->getTableName()));
    }
}
