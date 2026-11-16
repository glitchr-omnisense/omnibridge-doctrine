<?php

namespace Omnibridge\Doctrine\Tests\Omnibase;

use Base\Database\Attribute\Cascade;
use Base\Entity\Thread;
use Doctrine\DBAL\DriverManager;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\Omnibase\Mapping;
use Omnibridge\Doctrine\Tests\Fixtures\Cascades\Album;
use PHPUnit\Framework\TestCase;

/**
 * #[Cascade] and #[OrphanRemoval] have ONE reading, the core's Cascade::resolve() (Eloquent's cascades take it):
 * Doctrine's mapping of every association is what it answers.
 */
final class CascadesTest extends TestCase
{
    public function testDoctrinesMappingIsTheCoresReading(): void
    {
        if (!class_exists(Thread::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        $entityManager = EntityManagers::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), [\dirname((new \ReflectionClass(Thread::class))->getFileName()), \dirname(__DIR__).'/Fixtures/Cascades'], true, null, Mapping::configure(...));
        Mapping::register($entityManager, ['project_dir' => sys_get_temp_dir(), 'cache_dir' => sys_get_temp_dir().'/omnibridge-doctrine-cascades-test', 'parameters' => ['base.uploader.storage' => 'local.uploads']]);
        $album = $entityManager->getClassMetadata(Album::class);

        self::assertTrue($album->getAssociationMapping('children')->orphanRemoval, 'through the #[Alias] $photos');
        self::assertSame(['persist', 'remove'], $album->getAssociationMapping('likes')->cascade, 'from the class');
        self::assertFalse($entityManager->getClassMetadata(Thread::class)->getAssociationMapping('children')->orphanRemoval, 'the parent untouched');
        foreach ($album->associationMappings as $property => $mapping) {
            self::assertSame(Cascade::resolve(Album::class, $property, $mapping->cascade, $mapping->orphanRemoval), [$mapping->cascade, $mapping->orphanRemoval], $property);
        }
    }
}
