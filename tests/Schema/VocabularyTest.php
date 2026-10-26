<?php

namespace Omnibridge\Doctrine\Tests\Schema;

use Doctrine\ORM\Mapping\MappingAttribute;
use Omnibridge\Schema\Declaration\Vocabulary;
use PHPUnit\Framework\TestCase;

/**
 * The vocabulary omnisense/omnibridge holds as data is doctrine/orm's: every
 * mapping attribute, its constructor's parameters in order, their defaults,
 * whether it repeats - compared with the installed doctrine/orm, class by
 * class. A new attribute or parameter upstream fails here, and the vocabulary
 * is completed by hand.
 */
final class VocabularyTest extends TestCase
{
    public function testEveryAttributeOfTheVocabularyIsDoctrinesWithTheSameParameters(): void
    {
        foreach (Vocabulary::PARAMETERS as $name => $parameters) {
            $className = Vocabulary::NAMESPACE.$name;
            self::assertTrue(class_exists($className), $className);
            self::assertTrue(is_subclass_of($className, MappingAttribute::class), $className.' is a mapping attribute');

            $constructor = (new \ReflectionClass($className))->getConstructor();
            $expected = [];
            foreach ($constructor?->getParameters() ?? [] as $parameter) {
                $expected[$parameter->getName()] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : Vocabulary::REQUIRED;
            }
            self::assertSame(array_keys($expected), array_keys($parameters), $name.': the parameters, in order');
            self::assertSame($expected, $parameters, $name.': the defaults');

            // AssociationOverride, AttributeOverride and JoinColumns are written inside another attribute, not as one.
            $attribute = ((new \ReflectionClass($className))->getAttributes(\Attribute::class)[0] ?? null)?->newInstance();
            self::assertSame(null !== $attribute && ($attribute->flags & \Attribute::IS_REPEATABLE), Vocabulary::isRepeatable($name), $name.': repeatable');
        }
    }

    public function testEveryMappingAttributeOfDoctrineIsInTheVocabulary(): void
    {
        $directory = \dirname((new \ReflectionClass(MappingAttribute::class))->getFileName());
        $missing = [];
        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $className = Vocabulary::NAMESPACE.basename($file, '.php');
            if (!class_exists($className) || !is_subclass_of($className, MappingAttribute::class) || (new \ReflectionClass($className))->isAbstract()) {
                continue;
            }
            if (!(new \ReflectionClass($className))->getAttributes(\Attribute::class)) {
                continue;
            }
            if (null === Vocabulary::nameOf($className)) {
                $missing[] = $className;
            }
        }

        self::assertSame([], $missing, 'attributes doctrine/orm '.\Composer\InstalledVersions::getPrettyVersion('doctrine/orm').' has and the vocabulary lacks');
    }

    public function testTheLifecycleCallbacksAreDoctrinesEvents(): void
    {
        foreach (Vocabulary::CALLBACKS as $attribute => $event) {
            self::assertTrue(class_exists(Vocabulary::NAMESPACE.$attribute), $attribute);
            self::assertSame($event, \constant(\Doctrine\ORM\Events::class.'::'.$event));
        }
    }
}
