<?php

namespace Omnibridge\Doctrine\Tests\Schema;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\MetadataDescription;
use Omnibridge\Doctrine\NamingStrategy;
use Omnibridge\Doctrine\Tests\Fixtures\Generators\Assigned;
use Omnibridge\Doctrine\Tests\Fixtures\Generators\Token;
use Omnibridge\Doctrine\Tests\Fixtures\Generators\TokenGenerator;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\Harness;
use PHPUnit\Framework\TestCase;

/**
 * What doctrine/orm 3.8 changed in reading the attributes, read the same from the declarations (DeclarationDriver):
 * a #[CustomIdGenerator] without #[GeneratedValue] is the CUSTOM strategy, an explicit NONE keeps the identifier
 * assigned; a column's charset and collation stay in its options, which the schema editor API takes without
 * deprecation (MetadataDescription describes the tables on that API).
 */
final class GeneratorsAndColumnOptionsTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/Generators';

    public function testACustomGeneratorWithoutAStrategyIsTheCustomOneAsDoctrinesAttributeDriverReadsIt(): void
    {
        $doctrine = self::attributes();
        $declared = EntityManagers::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), [self::FIXTURES], true);

        foreach ([Token::class, Assigned::class] as $className) {
            self::assertSame(Harness::snapshot($doctrine->getClassMetadata($className)), Harness::snapshot($declared->getClassMetadata($className)), $className);
        }

        $token = $declared->getClassMetadata(Token::class);
        self::assertSame(ClassMetadata::GENERATOR_TYPE_CUSTOM, $token->generatorType);
        self::assertSame(['class' => TokenGenerator::class], $token->customGeneratorDefinition);
        self::assertInstanceOf(TokenGenerator::class, $token->idGenerator);
        self::assertSame(ClassMetadata::GENERATOR_TYPE_NONE, $declared->getClassMetadata(Assigned::class)->generatorType, 'an explicit NONE');
        self::assertSame(['charset' => 'ascii', 'collation' => 'ascii_bin'], $token->getFieldMapping('code')->options);
    }

    public function testTheCharsetAndCollationReachTheSchemaWithoutDeprecation(): void
    {
        // @phpstan-ignore function.alreadyNarrowedType (DBAL before 4.5 has no schema editor API)
        if (!method_exists(Schema::class, 'edit')) {
            self::markTestSkipped('the schema editor API comes with doctrine/dbal 4.5');
        }
        $entityManager = EntityManagers::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), [self::FIXTURES], true);
        self::assertFalse($entityManager->getConfiguration()->getUseSchemaEditorApi(), 'the host\'s configuration, left as it is');

        Deprecation::enableTrackingDeprecations();
        $before = Deprecation::getTriggeredDeprecations();
        $description = MetadataDescription::of($entityManager, [Token::class, Assigned::class]);
        $after = Deprecation::getTriggeredDeprecations();

        foreach (['https://github.com/doctrine/orm/pull/12780', 'https://github.com/doctrine/orm/pull/12733'] as $link) {
            self::assertSame($before[$link] ?? 0, $after[$link] ?? 0, $link);
        }
        self::assertFalse($entityManager->getConfiguration()->getUseSchemaEditorApi(), 'still the host\'s configuration');
        $table = $entityManager->getClassMetadata(Token::class)->getTableName();
        self::assertCount(2, $description->tables);
        self::assertSame(['id', 'code'], array_keys($description->table($table)->columns));
    }

    private static function attributes(): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([self::FIXTURES], true);
        $configuration->setNamingStrategy(new NamingStrategy());
        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
    }
}
