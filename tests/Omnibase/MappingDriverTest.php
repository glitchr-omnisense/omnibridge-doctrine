<?php

namespace Omnibridge\Doctrine\Tests\Omnibase;

use Base\Entity\Thread;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Mapping\MappingException;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\Omnibase\Mapping;
use Omnibridge\Doctrine\Tests\Fixtures\Outside\Post;
use PHPUnit\Framework\TestCase;

/**
 * An entity outside the entity manager's paths, its parent inside: Doctrine's MappingException naming it, never a
 * discriminator resolved round and round until the memory is exhausted.
 */
final class MappingDriverTest extends TestCase
{
    public function testAnEntityOutsideThePathsIsRefusedByName(): void
    {
        if (!class_exists(Thread::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        $entityManager = EntityManagers::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), [\dirname((string) (new \ReflectionClass(Thread::class))->getFileName()), \dirname(__DIR__).'/Fixtures/Inside'], true, null, Mapping::configure(...));
        Mapping::register($entityManager, ['project_dir' => sys_get_temp_dir(), 'cache_dir' => sys_get_temp_dir().'/omnibridge-doctrine-mapping-driver-test', 'parameters' => ['base.uploader.storage' => 'local.uploads']]);
        $memory = ini_get('memory_limit');
        ini_set('memory_limit', '256M');

        try {
            $entityManager->getClassMetadata(Post::class);
            self::fail('An entity outside the paths was mapped.');
        } catch (MappingException $exception) {
            self::assertStringContainsString(Post::class, $exception->getMessage());
        } finally {
            ini_set('memory_limit', (string) $memory);
        }
    }
}
