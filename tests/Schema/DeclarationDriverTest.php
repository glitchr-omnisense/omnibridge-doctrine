<?php

namespace Omnibridge\Doctrine\Tests\Schema;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Omnibridge\Doctrine\DeclarationDriver;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\NamingStrategy;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\Entity\Article;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\Entity\Thread;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\Harness;
use Omnibridge\Schema\CamelCaseNaming;
use Omnibridge\Schema\Declaration\AttributeReader;
use Omnibridge\Schema\Declaration\DeclarationSet;
use PHPUnit\Framework\TestCase;

/**
 * Doctrine's metadata built from the declarations (Omnibridge\Schema\Declaration)
 * are those its attribute driver builds, to the property: for every class of
 * the fixtures, read from the attributes, and read from declarations kept as
 * data (a JSON file: the classes' attributes are never looked at).
 */
final class DeclarationDriverTest extends TestCase
{
    private const DOCTRINE = 'Omnibridge\\Doctrine\\Tests\\Fixtures\\Schema\\Entity';
    private const BASE = 'Omnibridge\\Doctrine\\Tests\\Fixtures\\Base\\Entity';

    public function testTheMetadataAreTheAttributeDriversClassByClass(): void
    {
        $doctrine = Harness::entityManager();
        $declared = Harness::entityManager(static fn (MappingDriver $driver) => new DeclarationDriver($driver));

        self::assertInstanceOf(DeclarationDriver::class, $declared->getConfiguration()->getMetadataDriverImpl());
        self::assertSame($doctrine->getConfiguration()->getMetadataDriverImpl()->getAllClassNames(), $declared->getConfiguration()->getMetadataDriverImpl()->getAllClassNames(), 'the classes stay the wrapped driver\'s');

        $compared = 0;
        foreach (Harness::classes() as $className) {
            self::assertSame(Harness::snapshot($doctrine->getClassMetadata($className)), Harness::snapshot($declared->getClassMetadata($className)), $className);
            ++$compared;
        }
        self::assertSame(8, $compared);

        $article = $declared->getClassMetadata(Article::class);
        self::assertSame('article_keywords', $article->getAssociationMapping('keywords')->joinTable->name, 'a nested attribute (the join table\'s columns) read as written');
        self::assertSame('CASCADE', $article->getAssociationMapping('keywords')->joinTable->joinColumns[0]->onDelete);
        self::assertSame(['prePersist' => ['touch'], 'preUpdate' => ['touch']], $article->lifecycleCallbacks, 'the lifecycle callbacks, inherited');
        self::assertSame(\Doctrine\ORM\EntityRepository::class, $declared->getClassMetadata(Thread::class)->customRepositoryClassName);
    }

    /**
     * The fixtures written in omnibase's own vocabulary (Base\\Mapping, step E3; a nested attribute left in Doctrine's,
     * the core and its classes not being installed here): Doctrine's attribute driver sees no entity there, the
     * declaration driver lists and maps them all, with the metadata of the classes written in Doctrine's.
     */
    public function testTheClassesWrittenInOmnibasesVocabularyAreTheSameEntities(): void
    {
        $directory = sys_get_temp_dir().'/omnibridge-doctrine-e3-'.getmypid();
        @mkdir($directory, 0777, true);
        foreach (glob(\dirname(__DIR__).'/Fixtures/Schema/Entity/*.php') ?: [] as $file) {
            file_put_contents($directory.'/'.basename($file), str_replace(
                ['namespace '.self::DOCTRINE.';', 'use Doctrine\\ORM\\Mapping as ORM;', 'new ORM\\'],
                ['namespace '.self::BASE.';', 'use Base\\Mapping as ORM;', 'new \\Doctrine\\ORM\\Mapping\\'],
                (string) file_get_contents($file),
            ));
        }

        spl_autoload_register(static function (string $className) use ($directory): void {
            if (str_starts_with($className, self::BASE.'\\') && is_file($file = $directory.'/'.substr($className, \strlen(self::BASE) + 1).'.php')) {
                require_once $file;
            }
        });

        $doctrine = Harness::entityManager();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $base = EntityManagers::create($connection, [$directory], true, configure: static function (Configuration $configuration): void {
            $configuration->setNamingStrategy(new NamingStrategy(new CamelCaseNaming(['Omnibridge\\Doctrine\\Tests\\Fixtures\\Base\\'])));
            $attributes = $configuration->getMetadataDriverImpl();
            self::assertNotNull($attributes);
            $configuration->setMetadataDriverImpl(new DeclarationDriver($attributes));
        });

        $driver = $base->getConfiguration()->getMetadataDriverImpl();
        self::assertInstanceOf(DeclarationDriver::class, $driver);
        $doctrineDriver = $doctrine->getConfiguration()->getMetadataDriverImpl();
        self::assertNotNull($doctrineDriver);
        self::assertSame([], $driver->getDriver()->getAllClassNames(), 'Doctrine\'s attribute driver knows no attribute of Base\\Mapping');
        $expected = array_map(static fn (string $className) => str_replace(self::DOCTRINE, self::BASE, $className), $doctrineDriver->getAllClassNames());
        $listed = $driver->getAllClassNames();
        sort($expected);
        sort($listed);
        self::assertSame($expected, $listed, 'the same classes, listed by their declarations');
        self::assertFalse($driver->isTransient(self::written('Thread')));
        self::assertFalse($driver->isTransient(self::written('Timestamps')), 'a mapped superclass');

        foreach (Harness::classes() as $className) {
            $expected = json_encode(Harness::snapshot($doctrine->getClassMetadata($className)), \JSON_THROW_ON_ERROR);
            $actual = json_encode(Harness::snapshot($base->getClassMetadata(str_replace(self::DOCTRINE, self::BASE, $className))), \JSON_THROW_ON_ERROR);
            self::assertSame($expected, str_replace(addslashes(self::BASE), addslashes(self::DOCTRINE), $actual), $className);
        }
    }

    public function testTheMetadataFromDeclarationsKeptAsData(): void
    {
        $doctrine = Harness::entityManager();
        $json = json_encode(DeclarationSet::of(new AttributeReader(), Harness::classes())->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
        $set = DeclarationSet::fromArray(json_decode($json, true, 512, \JSON_THROW_ON_ERROR));
        $fromData = Harness::entityManager(static fn (MappingDriver $driver) => new DeclarationDriver($driver, $set));

        self::assertStringContainsString('"JoinTable"', $json);
        foreach (Harness::classes() as $className) {
            self::assertSame(Harness::snapshot($doctrine->getClassMetadata($className)), Harness::snapshot($fromData->getClassMetadata($className)), $className);
        }
    }

    public function testAClassTheDeclarationsDoNotHoldIsLeftToTheWrappedDriver(): void
    {
        $set = new DeclarationSet([]);
        $entityManager = Harness::entityManager(static fn (MappingDriver $driver) => new DeclarationDriver($driver, $set));

        self::assertSame('thread', $entityManager->getClassMetadata(Thread::class)->getTableName(), 'the attribute driver read it');
        self::assertFalse($entityManager->getConfiguration()->getMetadataDriverImpl()->isTransient(Thread::class));
    }

    public function testAClassThatIsNoEntityIsRefusedAsDoctrineRefusesIt(): void
    {
        $driver = new DeclarationDriver(new AttributeDriver([]), new DeclarationSet([(new AttributeReader())->read(Thread::class)]));
        $metadata = new \Doctrine\ORM\Mapping\ClassMetadata(\stdClass::class);
        $metadata->initializeReflection(new \Doctrine\Persistence\Mapping\RuntimeReflectionService());

        $this->expectException(MappingException::class);
        $driver->loadMetadataForClass(\stdClass::class, $metadata);
    }

    /**
     * A class of the fixtures written in Base\Mapping (testTheClassesWrittenInOmnibasesVocabularyAreTheSameEntities: loaded by its autoloader).
     *
     * @return class-string
     */
    private static function written(string $name): string
    {
        $className = self::BASE.'\\'.$name;
        self::assertTrue(class_exists($className), $className);
        /** @var class-string $className */
        return $className;
    }
}
