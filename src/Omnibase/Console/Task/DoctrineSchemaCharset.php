<?php

namespace Omnibridge\Doctrine\Omnibase\Console\Task;

use Base\Console\Report;
use Base\Console\Task\ThreadPublishable;
use Doctrine\DBAL\Connection;

/**
 * doctrine:schema:charset (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 3; Doctrine-typed, so this
 * package's): the database's charset and collation and each table's collation, MySQL's; altered when asked, to the
 * charset and collation given, else the connection's, else the server's.
 */
final class DoctrineSchemaCharset
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function run(DoctrineSchemaCharsetInput $input, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        $params = $this->connection->getParams();
        $databaseName = $params['dbname'] ?? null;
        $tableNames = $input->table ? [$input->table] : $this->connection->createSchemaManager()->listTableNames();

        $charsetDefault = $this->databaseVariable('character_set_database');
        $charset = $input->charset ?? $params['charset'] ?? $charsetDefault;
        $collateDefault = $this->databaseVariable('collation_database');
        $collate = $input->collate ?? $params['defaultTableOptions']['collate'] ?? $collateDefault;

        $report->line('');
        if (!($input->update && ($charset != $charsetDefault || $collate != $collateDefault))) {
            $report->line('<info>Database selected:</info> '.$databaseName);
            $report->line("\t- Charset: <warning>".$charset.'</warning>');
            $report->line("\t- Collation: <warning>".$collate.'</warning>');
        } else {
            $report->line("\nUpdating database schema...");
            if ($databaseName) {
                $this->connection->executeStatement('ALTER DATABASE '.$databaseName.' CHARACTER SET '.$charset.' COLLATE '.$collate);
            }
            ThreadPublishable::banner($report, 'info', ' [OK] Database `'.$databaseName.'` altered.. (charset, collate)=('.$charset.', '.$collate.')', false);
        }

        if (!$input->updateTables) {
            $report->line('<info>Table list:</info> ');
            foreach ($tableNames as $tableName) {
                $report->line("\t- ".$tableName.'; Collation = <warning>'.$this->tableVariable($tableName, 'Collation').'</warning>');
            }
        } else {
            $report->line("\nUpdating table schema...");
            $count = 0;
            $tableCollate = $input->tableCollate ?? $params['defaultTableOptions']['collate'] ?? null;
            foreach ($tableNames as $tableName) {
                $count += (int) $this->connection->executeStatement('ALTER TABLE '.$tableName.' CONVERT TO CHARACTER SET '.$charset.' COLLATE '.$collate);
            }
            ThreadPublishable::banner($report, 'info', ' [OK] '.$count.' table column(s) altered.. (charset, collate)=('.$charset.', '.$tableCollate.') ', false);
        }

        return $report->line('');
    }

    private function databaseVariable(string $name): ?string
    {
        return $this->connection->executeQuery("SHOW VARIABLES LIKE '".$name."'")->fetchAllKeyValue()[$name] ?? null;
    }

    private function tableVariable(string $table, string $name): ?string
    {
        return $this->connection->executeQuery("SHOW TABLE STATUS WHERE NAME LIKE '".$table."'")->fetchAllAssociativeIndexed()[$table][$name] ?? null;
    }
}
