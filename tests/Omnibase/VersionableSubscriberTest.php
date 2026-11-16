<?php

namespace Omnibridge\Doctrine\Tests\Omnibase;

use Base\Entity\Extension\Revision;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Tools\SchemaTool;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\Omnibase\Mapping;
use Omnibridge\Doctrine\Tests\Fixtures\Versioned\Note;
use PHPUnit\Framework\TestCase;

/**
 * VersionableSubscriber trims a history after the flush through omnibase's repository of the revisions
 * (RevisionRepository::obsoleteIds()), not Doctrine's getRepository(), which answers its plain repository: a
 * versioned entity flushed past `max_revisions` keeps its newest revisions only (es-2's doctrine:fixtures:load
 * died on "Undefined method obsoleteIds").
 */
final class VersionableSubscriberTest extends TestCase
{
    public function testTheObsoleteRevisionsArePurgedAfterTheFlush(): void
    {
        if (!class_exists(Revision::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        $core = \dirname((string) (new \ReflectionClass(Revision::class))->getFileName(), 2);
        $entityManager = EntityManagers::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), [\dirname(__DIR__).'/Fixtures/Versioned', $core], true, configure: static function (Configuration $configuration): void {
            Mapping::configure($configuration);
        });
        Mapping::register($entityManager, ['filters' => false, 'project_dir' => sys_get_temp_dir(), 'cache_dir' => sys_get_temp_dir().'/omnibridge-doctrine-versionable-test', 'parameters' => ['base.extension.max_revisions' => 1, 'base.uploader.storage' => 'local.uploads']]);
        // A revision reads its entity's class through the runtime's unit of work, as a host fills it at boot.
        \Omnibridge\Runtime::set(\Omnibridge\Persistence\UnitOfWork::class, \Base\Database\UnitOfWorks::of($entityManager));
        (new SchemaTool($entityManager))->createSchema(array_map($entityManager->getClassMetadata(...), [Note::class, \Base\Entity\Extension\Abstract\AbstractExtension::class, Revision::class]));

        $note = new Note();
        $note->title = 'first';
        $entityManager->persist($note);
        $entityManager->flush();

        foreach (['second', 'third'] as $title) {
            $note->title = $title;
            $entityManager->flush();
        }

        $entityManager->clear();
        /** @var list<Revision> $revisions */
        $revisions = $entityManager->createQuery('SELECT r FROM '.Revision::class.' r ORDER BY r.id')->getResult();
        self::assertCount(1, $revisions, 'max_revisions 1: the older revision is purged');
        self::assertSame(['title' => ['second', 'third']], $revisions[0]->getEntityData(), 'the newest one is kept');
    }

    protected function tearDown(): void
    {
        \Omnibridge\Runtime::set(\Omnibridge\Persistence\UnitOfWork::class, null);
    }
}
