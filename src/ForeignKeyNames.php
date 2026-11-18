<?php

namespace Omnibridge\Doctrine;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * The foreign keys' names of before the schema editor API, kept (4.0).
 *
 * doctrine/orm 3.8's SchemaTool on the schema editor API leaves a foreign key unnamed, where DBAL's mutable Table named
 * it "FK_" and the CRC32 of the table's and the referencing columns' names (Table::_generateIdentifierName()): MySQL
 * then names a key it creates `<table>_ibfk_<n>`, and a new table's or a new key's name would differ from the ones
 * every site's migrations wrote. On ToolEvents::postGenerateSchema, each foreign key left unnamed is given the name of
 * before; a key named by the mapping keeps its own. EntityManagers::create() listens with it; a host's entity manager
 * is given it as a postGenerateSchema listener.
 */
final class ForeignKeyNames
{
    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $schema = $event->getSchema();
        $length = $event->getEntityManager()->getConnection()->getDatabasePlatform()->getMaxIdentifierLength();
        $tables = [];
        $named = false;
        foreach ($schema->getTables() as $table) {
            $keys = [];
            $unnamed = false;
            foreach ($table->getForeignKeys() as $key) {
                if (null === $key->getObjectName()) {
                    $columns = array_map(static fn (UnqualifiedName $name): string => $name->toString(), $key->getReferencingColumnNames());
                    $key = $key->edit()->setName(UnqualifiedName::unquoted(self::name(self::qualified($table->getObjectName()), $columns, $length)))->create();
                    $unnamed = true;
                }
                $keys[] = $key;
            }
            $tables[] = $unnamed ? $table->edit()->setForeignKeyConstraints(...$keys)->create() : $table;
            $named = $named || $unnamed;
        }
        if ($named) {
            $event->setSchema($schema->edit()->setTables(...$tables)->create());
        }
    }

    /**
     * The name DBAL's mutable Table gives a foreign key: "FK_" and the CRC32 of the table's and the columns' names in
     * hexadecimal, upper-cased, cut to the platform's identifier length.
     *
     * @param list<string> $columns
     *
     * @return non-empty-string
     */
    public static function name(string $table, array $columns, int $length): string
    {
        return strtoupper(substr('fk_'.implode('', array_map(static fn (string $name): string => dechex(crc32($name)), [$table, ...$columns])), 0, max(3, $length)));
    }

    /** A table's name as DBAL's mutable Table's getName() gave it: the qualifier's value and a dot before the name's. */
    private static function qualified(OptionallyQualifiedName $name): string
    {
        $qualifier = $name->getQualifier();

        return (null === $qualifier ? '' : $qualifier->getValue().'.').$name->getUnqualifiedName()->getValue();
    }
}
