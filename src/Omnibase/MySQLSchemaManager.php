<?php

namespace Omnibridge\Doctrine\Omnibase;

use Base\Database\Type\MySQLSetType;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\MySQLSchemaManager as DbalMySQLSchemaManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;

/**
 * DBAL's MySQL schema manager, with two things a column read back from the
 * database needs to compare equal to its mapping (plan "omnibase agnostique",
 * C6b; doctrine/dbal as published, without glitchr/doctrine-dc2type):
 *
 * - a SET column (Base\Database\Type\SetType: user roles, user states) is
 *   read with its values, as DBAL reads an ENUM column since 4.2: its type is
 *   Base\Database\Type\MySQLSetType, whose declaration is the SET(...) of the
 *   values the database has. DBAL reads SET as simple_array (LONGTEXT), a
 *   column every comparison found changed;
 * - the "(DC2Type:...)" a column's comment holds from before is left out,
 *   with the values Base\DatabaseSubscriber\EnumSubscriber wrote after it on
 *   an ENUM or SET column ("(DC2Type:user_role) ROLE_ADMIN,ROLE_USER"): DBAL 4
 *   no longer writes these comments, nor does omnibase since 4.0, so they are
 *   no part of a column; a database that still has them needs no migration
 *   (DBAL 3 left them out on reading as well). Any other comment is kept.
 *
 * An ENUM or SET column's values come from the database, so a value added to
 * an enumeration (an application's ROLE_PRO) is a column changed: the schema
 * tool and doctrine:migrations:diff alter it.
 */
class MySQLSchemaManager extends DbalMySQLSchemaManager
{
    private const LEGACY_TYPE_COMMENT = '/\(DC2Type:[^)]+\)/';

    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $column = parent::_getPortableTableColumnDefinition($tableColumn);
        $row = array_change_key_case($tableColumn, CASE_LOWER);

        $set = strtolower((string) ($row['type'] ?? '')) === 'set';
        $values = $set ? self::values((string) ($row['column_type'] ?? '')) : $column->getValues();
        if ($set) {
            // DBAL's registry (Type's static methods are deprecated since DBAL 4.6): the type provider of a
            // connection configured without one, Type::getTypeRegistry() before 4.6.
            $configuration = new Configuration();
            // @phpstan-ignore function.alreadyNarrowedType (DBAL 4.2 to 4.5, which composer.json still allows, has no type provider)
            $registry = method_exists($configuration, 'getTypeProvider') ? $configuration->getTypeProvider() : Type::getTypeRegistry();
            \assert($registry instanceof TypeRegistry);
            if (!$registry->has(MySQLSetType::NAME)) {
                $registry->register(MySQLSetType::NAME, MySQLSetType::class);
            }
        }

        $comment = $column->getComment();
        $legacy = $comment !== '' && preg_match(self::LEGACY_TYPE_COMMENT, $comment) === 1;
        if ($legacy) {
            $comment = trim((string) preg_replace(self::LEGACY_TYPE_COMMENT, '', $comment));
            // The values EnumSubscriber wrote after the type: the column's own, in the same ALTER.
            if ($values !== [] && self::sameValues(explode(',', $comment), $values)) {
                $comment = '';
            }
        }

        if (!$set && !$legacy) {
            return $column;
        }

        // The column made again by its editor where DBAL has it (4.5; its setters are deprecated there), with the
        // connection's types as DBAL's own schema manager gives them; set in place before.
        // @phpstan-ignore function.alreadyNarrowedType (DBAL 4.2 to 4.4, which composer.json still allows, has no column editor)
        if (method_exists($column, 'edit')) {
            $editor = $column->edit()->setComment($comment);
            if ($set) {
                $editor->setTypeName(MySQLSetType::NAME)->setValues($values);
            }
            $edited = $editor->create();
            $configuration = $this->connection->getConfiguration();
            // @phpstan-ignore function.alreadyNarrowedType (DBAL 4.5, which composer.json still allows, has no type provider)
            if (method_exists($configuration, 'getTypeProvider')) {
                $edited->setTypeProvider($configuration->getTypeProvider());
            }

            return $edited;
        }

        if ($set) {
            $column->setType(Type::getType(MySQLSetType::NAME));
            $column->setValues($values);
        }
        $column->setComment($comment);

        return $column;
    }

    /** @return list<string> the values of "set('a','b')" (or "enum(...)"), as DBAL reads an ENUM's */
    public static function values(string $columnType): array
    {
        preg_match_all("/'([^']*(?:''[^']*)*)'/", $columnType, $matches);

        return array_map(static fn (string $value): string => strtr($value, ["''" => "'"]), $matches[1]);
    }

    /** @param list<string> $a @param list<string> $b */
    private static function sameValues(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }
}
