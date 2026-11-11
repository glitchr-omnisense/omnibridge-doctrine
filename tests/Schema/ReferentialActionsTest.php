<?php

namespace Omnibridge\Doctrine\Tests\Schema;

use Doctrine\DBAL\DriverManager;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\MetadataDescription;
use Omnibridge\Doctrine\NamingStrategy;
use Omnibridge\Doctrine\Tests\Fixtures\Referential\Holding;
use Omnibridge\Doctrine\Tests\Fixtures\Referential\Owner;
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\CamelCaseNaming;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * A foreign key's ON DELETE in Doctrine's description (MetadataDescription), read from DBAL's referential actions
 * (getOnDeleteAction(), getOnUpdateAction()) and not from onDelete() and onUpdate(), deprecated since DBAL 4.5: in
 * upper case, null for RESTRICT and NO ACTION (omnisense/omnibridge's ForeignKey::action()), so that the description
 * built without Doctrine stays Doctrine's for each value an entity may declare.
 */
final class ReferentialActionsTest extends TestCase
{
    private const PREFIX = 'Omnibridge\\Doctrine\\Tests\\Fixtures\\';

    protected function tearDown(): void
    {
        Deprecation::disable();
    }

    public function testEachOnDeleteIsTheDescriptionBuiltWithoutDoctrine(): void
    {
        $doctrine = MetadataDescription::of(self::entityManager());
        $built = (new Builder(naming: new CamelCaseNaming([self::PREFIX])))->build([Owner::class, Holding::class]);

        self::assertSame(array_keys($built->tables), array_keys($doctrine->tables));
        foreach ($doctrine->tables as $name => $table) {
            self::assertSame($built->table($name)->toArray(), $table->toArray(), 'table '.$name);
        }
        self::assertSame($built->toJson(), $doctrine->toJson());

        $onDelete = [];
        foreach ($doctrine->table('referentialHolding')->foreignKeys as $foreignKey) {
            $onDelete[$foreignKey->columns[0]] = $foreignKey->onDelete;
            self::assertNull($foreignKey->onUpdate, $foreignKey->columns[0].': no ON UPDATE, Doctrine\'s mapping declares none');
        }
        ksort($onDelete);

        self::assertSame([
            'cascaded_id' => 'CASCADE',
            'noActionLower_id' => null,
            'noAction_id' => null,
            'plain_id' => null,
            'restrictedLower_id' => null,
            'restricted_id' => null,
            'setNull_id' => 'SET NULL',
        ], $onDelete);
    }

    public function testNeitherOnDeleteNorOnUpdateIsCalled(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
        Deprecation::enableWithPsrLogger($logger);
        Deprecation::withoutDeduplication();

        MetadataDescription::of(self::entityManager());

        $actions = array_values(array_filter($logger->messages, static fn (string $message): bool => str_contains($message, '::onDelete') || str_contains($message, '::onUpdate')));
        self::assertSame([], $actions);
    }

    private static function entityManager(): EntityManager
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        return EntityManagers::create($connection, [__DIR__.'/../Fixtures/Referential'], true, configure: static function (Configuration $configuration): void {
            $configuration->setNamingStrategy(new NamingStrategy(new CamelCaseNaming([self::PREFIX])));
        });
    }
}
