<?php

namespace Omnibridge\Doctrine\Omnibase\Console\Task;

/** doctrine:schema:charset's options (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 3). */
final readonly class DoctrineSchemaCharsetInput
{
    /**
     * @param bool    $update       the database's charset and collation altered
     * @param bool    $updateTables the tables converted
     * @param ?string $table        this table only
     */
    public function __construct(
        public bool $update = false,
        public ?string $charset = null,
        public ?string $collate = null,
        public bool $updateTables = false,
        public ?string $table = null,
        public ?string $tableCollate = null,
    ) {
    }
}
