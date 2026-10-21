<?php

namespace Omnibridge\Doctrine\Tests;

use Omnibridge\Doctrine\NamingStrategy;
use Omnibridge\Schema\CamelCaseNaming;
use PHPUnit\Framework\TestCase;

/**
 * omnibase's names (Omnibridge\Schema\CamelCaseNaming, answered to Doctrine by
 * NamingStrategy), against a real site's: every entity of lesringostars and
 * the table omnibase gives it, its join tables and their columns
 * (lesringostars-tables.json, read from the Symfony site's entity manager).
 * The tables a class names itself (#[Table(name: ...)]) are read from the
 * class in an application; here, where the classes are not, the file says them.
 */
final class NamingStrategyTest extends TestCase
{
    /** @return array<string, array{table: string, declared: bool, joins: array<string, array{0: string, 1: ?string, 2: ?string, 3: string, 4: string, 5: bool}>|list<never>}> */
    private static function site(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/lesringostars-tables.json'), true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function strategy(): NamingStrategy
    {
        $declared = array_map(static fn (array $row) => $row['table'], array_filter(self::site(), static fn (array $row) => $row['declared']));

        // The naming answered through the strategy, as Doctrine asks it (one place: CamelCaseNaming).
        return new NamingStrategy(new class($declared) extends CamelCaseNaming {
            public function __construct(private readonly array $declared)
            {
                parent::__construct();
            }

            public function classToTableName(string $className): string
            {
                return $this->declared[$className] ?? parent::classToTableName($className);
            }
        });
    }

    public function testEveryTableOfTheSite(): void
    {
        $strategy = new NamingStrategy();
        $checked = 0;
        foreach (self::site() as $class => $row) {
            if ($row['declared']) {
                continue;
            }
            self::assertSame($row['table'], $strategy->classToTableName($class), $class);
            ++$checked;
        }
        self::assertGreaterThan(100, $checked);
    }

    public function testEveryJoinTableAndItsColumns(): void
    {
        $strategy = self::strategy();
        $checked = 0;
        foreach (self::site() as $row) {
            foreach ($row['joins'] ?: [] as $property => [$table, $column, $inverse, $target, $owner]) {
                // A table joining a class to itself (connexes) names its columns: two thread_id cannot be.
                if ($strategy->classToTableName($owner) === $strategy->classToTableName($target)) {
                    continue;
                }
                self::assertSame([$table, $column, $inverse], [
                    $strategy->joinTableName($owner, $target, $property),
                    $strategy->joinKeyColumnName($owner, null),
                    $strategy->joinKeyColumnName($target, null),
                ], $owner.'::$'.$property);
                ++$checked;
            }
        }
        self::assertGreaterThan(20, $checked);
    }

    public function testTheRulesOnTheirOwn(): void
    {
        $strategy = new NamingStrategy();

        self::assertSame('threadTag', $strategy->classToTableName('App\Entity\Thread\Tag'));
        self::assertSame('threadIntl', $strategy->classToTableName('Base\Entity\ThreadIntl'));
        self::assertSame('articleCommentReply', $strategy->classToTableName('App\Entity\Article\CommentReply'));
        self::assertSame('thread_tags', $strategy->joinTableName('Base\Entity\Thread', 'App\Entity\Thread\Tag', 'tags'));
        self::assertSame('publishedAt', $strategy->propertyToColumnName('publishedAt', 'Base\Entity\Thread'));
        self::assertSame('translatable_id', $strategy->joinColumnName('translatable', 'Base\Entity\ThreadIntl'));
    }
}
