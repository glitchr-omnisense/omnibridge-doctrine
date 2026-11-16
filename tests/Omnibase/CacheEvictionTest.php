<?php

namespace Omnibridge\Doctrine\Tests\Omnibase;

use Base\Database\Repository\Finder\Change;
use Base\Database\Repository\ServiceEntityRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Tools\SchemaTool;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\Omnibase\DoctrineFinders;
use Omnibridge\Doctrine\Tests\Fixtures\Cached\Wallet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/** updateBy() and deleteBy() change rows behind Doctrine's back: the class's second-level cache entries go with them. */
final class CacheEvictionTest extends TestCase
{
    public function testAChangedOrDeletedRowIsNotReadFromTheCache(): void
    {
        if (!class_exists(ServiceEntityRepository::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        $entityManager = EntityManagers::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), [\dirname(__DIR__).'/Fixtures/Cached'], true, configure: static function (Configuration $configuration): void {
            $cache = new CacheConfiguration();
            $cache->setCacheFactory(new DefaultCacheFactory(new RegionsConfiguration(), new ArrayAdapter()));
            $configuration->setSecondLevelCacheEnabled(true);
            $configuration->setSecondLevelCacheConfiguration($cache);
        });
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Wallet::class)]);
        $wallet = new Wallet();
        $wallet->balance = 10;
        $entityManager->persist($wallet);
        $entityManager->flush();
        $id = $wallet->id;
        self::assertNotNull($id);
        $entityManager->clear();
        /** @var ServiceEntityRepository<Wallet> $wallets */
        $wallets = new ServiceEntityRepository(DoctrineFinders::over($entityManager), Wallet::class);
        self::assertSame(10, $wallets->find($id)?->balance);   // now in the cache
        $entityManager->clear();

        self::assertSame(1, $wallets->updateBy(['id' => $id], ['balance' => Change::add(-3)]));
        $entityManager->clear();
        // @phpstan-ignore nullsafe.neverNull (PHPStan keeps find()'s first answer; read again after updateBy(), it may be null)
        self::assertSame(7, $wallets->find($id)?->balance, 'not the cached 10');

        $entityManager->clear();
        self::assertSame(1, $wallets->deleteBy(['id' => $id]));
        $entityManager->clear();
        self::assertNull($wallets->find($id), 'not the cached row');
    }

    /** omnibase's subscribers on a second-level cache (ExtensionSubscriber's eviction): an entity with no getId() is persisted. */
    public function testAnEntityWithoutAGetterIsPersistedUnderTheCache(): void
    {
        if (!class_exists(\Base\Entity\Thread::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        $entityManager = EntityManagers::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), [\dirname(__DIR__).'/Fixtures/Cached'], true, configure: static function (Configuration $configuration): void {
            \Omnibridge\Doctrine\Omnibase\Mapping::configure($configuration);
            $cache = new CacheConfiguration();
            $cache->setCacheFactory(new DefaultCacheFactory(new RegionsConfiguration(), new ArrayAdapter()));
            $configuration->setSecondLevelCacheEnabled(true);
            $configuration->setSecondLevelCacheConfiguration($cache);
        });
        \Omnibridge\Doctrine\Omnibase\Mapping::register($entityManager, ['project_dir' => sys_get_temp_dir(), 'cache_dir' => sys_get_temp_dir().'/omnibridge-doctrine-extension-cache-test']);
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Wallet::class)]);
        self::assertFalse(method_exists(Wallet::class, 'getId'));

        $wallet = new Wallet();
        $wallet->balance = 3;
        $entityManager->persist($wallet);
        $entityManager->flush();
        self::assertNotNull($wallet->id);
    }

    /**
     * A member leaving a team (User::removeGroup()): after the flush, neither side's cached collection still holds the
     * link - the owning side's refreshed by Doctrine, the inverse side's evicted by omnibase's InverseCollectionCacheSubscriber.
     */
    public function testAManyToManyLinkRemovedLeavesNoCachedCollection(): void
    {
        if (!class_exists(\Base\Entity\Thread::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        $entityManager = EntityManagers::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), [\dirname(__DIR__).'/Fixtures/Cached'], true, configure: static function (Configuration $configuration): void {
            \Omnibridge\Doctrine\Omnibase\Mapping::configure($configuration);
            $cache = new CacheConfiguration();
            $cache->setCacheFactory(new DefaultCacheFactory(new RegionsConfiguration(), new ArrayAdapter()));
            $configuration->setSecondLevelCacheEnabled(true);
            $configuration->setSecondLevelCacheConfiguration($cache);
        });
        \Omnibridge\Doctrine\Omnibase\Mapping::register($entityManager, ['project_dir' => sys_get_temp_dir(), 'cache_dir' => sys_get_temp_dir().'/omnibridge-doctrine-collection-cache-test']);
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(\Omnibridge\Doctrine\Tests\Fixtures\Cached\Member::class), $entityManager->getClassMetadata(\Omnibridge\Doctrine\Tests\Fixtures\Cached\Team::class)]);
        $member = new \Omnibridge\Doctrine\Tests\Fixtures\Cached\Member();
        $team = new \Omnibridge\Doctrine\Tests\Fixtures\Cached\Team();
        $member->teams->add($team);
        $team->members->add($member);
        $entityManager->persist($member);
        $entityManager->flush();
        [$memberId, $teamId] = [$member->id, $team->id];
        $entityManager->clear();
        // Both collections read once: now in the cache.
        self::assertCount(1, $entityManager->find(\Omnibridge\Doctrine\Tests\Fixtures\Cached\Member::class, $memberId)->teams ?? []);
        self::assertCount(1, $entityManager->find(\Omnibridge\Doctrine\Tests\Fixtures\Cached\Team::class, $teamId)->members ?? []);
        $entityManager->clear();

        $member = $entityManager->find(\Omnibridge\Doctrine\Tests\Fixtures\Cached\Member::class, $memberId);
        self::assertNotNull($member);
        $team = $member->teams->first();
        self::assertInstanceOf(\Omnibridge\Doctrine\Tests\Fixtures\Cached\Team::class, $team);
        // Only the owning side, as User::removeGroup() does: the team's cached members must follow anyway.
        $member->teams->removeElement($team);
        $entityManager->flush();
        $entityManager->clear();

        self::assertCount(0, $entityManager->find(\Omnibridge\Doctrine\Tests\Fixtures\Cached\Member::class, $memberId)->teams ?? [], 'the owning side');
        self::assertCount(0, $entityManager->find(\Omnibridge\Doctrine\Tests\Fixtures\Cached\Team::class, $teamId)->members ?? [], 'the inverse side');
        self::assertNotNull($entityManager->find(\Omnibridge\Doctrine\Tests\Fixtures\Cached\Team::class, $teamId), 'the team kept');
    }
}
