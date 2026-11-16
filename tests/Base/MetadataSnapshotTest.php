<?php

namespace Omnibridge\Doctrine\Tests\Base;

use App\Entity\Thread;
use Base\Testing\MetadataSnapshot;
use Doctrine\DBAL\DriverManager;
use Omnibridge\Doctrine\EntityManagers;
use PHPUnit\Framework\TestCase;

/**
 * Base\Testing\MetadataSnapshot, this package's under omnibase's name (the one test tool a site names: its mapping
 * parity test, lesringostars-laravel's OmnibaseMappingTest): an entity manager's metadata as plain data.
 */
final class MetadataSnapshotTest extends TestCase
{
    public function testAnEntityManagersMetadataAreSnapshotAsPlainData(): void
    {
        $entityManager = EntityManagers::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), [__DIR__.'/../Fixtures/Entity']);

        $snapshot = MetadataSnapshot::of($entityManager, [Thread::class]);

        self::assertSame([Thread::class], array_keys($snapshot['classes']));
        self::assertSame('thread', $snapshot['classes'][Thread::class]['table']['name']);
        self::assertArrayHasKey('slug', $snapshot['classes'][Thread::class]['fieldMappings']);
        self::assertSame($snapshot, json_decode((string) json_encode($snapshot), true), 'plain data: it goes through JSON unchanged');
        self::assertArrayHasKey('string', $snapshot['types']);
    }
}
