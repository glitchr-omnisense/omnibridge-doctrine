<?php

namespace Omnibridge\Doctrine\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Tools\SchemaTool;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\Harness;
use PHPUnit\Framework\TestCase;

/**
 * 4.0: the schema editor API on, the foreign keys keep the names the SchemaTool gave them before (FK_ and a CRC32),
 * those every site's migrations wrote; MySQL would otherwise name a new key `<table>_ibfk_<n>`.
 */
final class ForeignKeyNamesTest extends TestCase
{
    public function testTheCreateStatementsAreThoseOfBeforeTheSchemaEditorApi(): void
    {
        $sql = [];
        foreach (['on' => true, 'off' => false] as $mode => $on) {
            $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);   // never connected
            $entityManager = EntityManagers::create($connection, [\dirname(__DIR__).'/tests/Fixtures/Schema/Entity'], true, configure: static fn (Configuration $configuration) => $on ? null : $configuration->setUseSchemaEditorApi(false));
            $sql[$mode] = (new SchemaTool($entityManager))->getCreateSchemaSql(array_map($entityManager->getClassMetadata(...), Harness::classes()));
        }

        self::assertNotEmpty(preg_grep('/ADD CONSTRAINT FK_[0-9A-F]+ FOREIGN KEY/', $sql['on']), 'named keys');
        self::assertSame([], preg_grep('/ADD FOREIGN KEY/', $sql['on']), 'no key left unnamed');
        self::assertSame($sql['off'], $sql['on']);
    }
}
