<?php

namespace Omnibridge\Doctrine\Omnibase\Console\Task;

use Base\Console\Report;
use Doctrine\DBAL\Connection;

/**
 * doctrine:array:upgrade (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 3; Doctrine-typed, so this
 * package's): the columns DBAL 3 stored serialized arrays in (a LONGTEXT commented "(DC2Type:array)") rewritten as
 * JSON and altered to JSON, the JSON columns' serialized values rewritten too. The columns are read as MySQL has
 * them, comments included: the schema manager leaves the former "(DC2Type:...)" comments out (MySQLSchemaManager).
 */
final class DoctrineArrayUpgrade
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function run(?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        $columns = $this->connection->fetchAllAssociative(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION'
        );

        foreach ($columns as $column) {
            $this->convert($column, $report);
        }

        return $report->line('All array fields converted to JSON successfully.');
    }

    /** @param array{TABLE_NAME: string, COLUMN_NAME: string, DATA_TYPE: string, COLUMN_COMMENT: ?string} $column */
    private function convert(array $column, Report $report): void
    {
        $tableName = $column['TABLE_NAME'];
        $columnName = $column['COLUMN_NAME'];
        $comment = (string) $column['COLUMN_COMMENT'];

        $isArray = str_contains($comment, '(DC2Type:array)');
        $isJson = 'json' === strtolower($column['DATA_TYPE']) || str_contains($comment, '(DC2Type:json)');
        if (!$isArray && !$isJson) {
            return;
        }

        try {
            $this->rows($tableName, $columnName, $report);
            if ($isArray) {
                $this->alter($tableName, $columnName, $report);
            }
        } catch (\Exception $e) {
            $report->line("Error converting `$columnName` in `$tableName`: ".$e->getMessage());
        }
    }

    private function rows(string $tableName, string $columnName, Report $report): void
    {
        $rows = $this->connection->createQueryBuilder()->select('id', $columnName)->from($tableName)->executeQuery()->fetchAllAssociative();
        foreach ($rows as $row) {
            $value = $row[$columnName];
            $unserializedValue = null;
            if ('' == $value) {
                $unserializedValue = [];
            } elseif (is_json($value)) {
                continue;
            } elseif (null !== $value) {
                $unserializedValue = unserialize($value);
                if (false === $unserializedValue && 'b:0;' !== $value) {
                    // Neither serialized nor 'b:0;': likely corrupt.
                    $report->line("Skipping row in $tableName: Unable to unserialize value in $columnName");
                    continue;
                }
            }

            $this->connection->executeStatement("UPDATE $tableName SET $columnName = :jsonValue WHERE id = :id", [
                'jsonValue' => json_encode($unserializedValue),
                'id' => $row['id'],
            ]);
        }

        $report->line("Converted $columnName in $tableName to JSON format.");
    }

    private function alter(string $tableName, string $columnName, Report $report): void
    {
        try {
            $this->connection->executeStatement("ALTER TABLE $tableName MODIFY $columnName JSON");
            $report->line("Altered $columnName in $tableName to JSON format.");
            $report->line('--> You can now change attributes/attributes column information in entities to `json`.');
        } catch (\Exception $e) {
            $report->line("Error altering $columnName in $tableName: ".$e->getMessage());
        }
    }
}
