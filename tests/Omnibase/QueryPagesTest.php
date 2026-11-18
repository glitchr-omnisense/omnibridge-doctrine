<?php

namespace Omnibridge\Doctrine\Tests\Omnibase;

use App\Entity\Thread;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Doctrine\ORM\Tools\SchemaTool;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\Omnibase\QueryPages;
use PHPUnit\Framework\TestCase;

/** A DQL query paged: its slices, its count, and doctrine/orm 3.8's OffsetPaginator over the same paged query (4.0). */
final class QueryPagesTest extends TestCase
{
    public function testAPageIsTheSameThroughSliceAndThroughTheOffsetPaginator(): void
    {
        if (!interface_exists(\Base\Service\Model\Pages::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        $entityManager = EntityManagers::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), [\dirname(__DIR__).'/Fixtures/Entity']);
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Thread::class)]);
        foreach (range(1, 5) as $i) {
            $thread = new Thread();
            $thread->slug = 'thread-'.$i;
            $entityManager->persist($thread);
        }
        $entityManager->flush();
        $entityManager->clear();

        $pages = new QueryPages($entityManager->createQuery('SELECT t FROM '.Thread::class.' t ORDER BY t.id ASC'));
        $slugs = static fn (iterable $threads): array => array_map(static fn (mixed $thread): ?string => $thread instanceof Thread ? $thread->slug : null, [...$threads]);

        self::assertSame(5, $pages->count());
        self::assertSame(['thread-3', 'thread-4'], $slugs($pages->slice(2, 2)));
        $paginator = $pages->paginator();
        self::assertInstanceOf(OffsetPaginator::class, $paginator);
        $page = $paginator->paginate($pages->query(0, 2), Window::fromPageNumberAndSize(2, 2));
        self::assertSame(['thread-3', 'thread-4'], $slugs($page));
        self::assertSame(5, $page->getTotalCount());
    }
}
