<?php

namespace Omnibridge\Doctrine\Tests\Schema;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Omnibridge\Doctrine\DeclarationDriver;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\Entity\Article;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\Entity\Thread;
use Omnibridge\Doctrine\Tests\Fixtures\Schema\Harness;
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
}
