<?php

namespace Omnibridge\Doctrine\Omnibase\Console\Task;

/** doctrine:database:import's argument and options (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 3). */
final readonly class DoctrineDatabaseImportInput
{
    /**
     * @param ?string       $path         the file (a path or a URL); asked when not given
     * @param ?list<string> $spreadsheets the sheets imported, by position (from 0); chosen when not given
     * @param ?string       $extension    the file's kind (xls, xlsx, xlsm, xml, csv, txt) where its type does not say
     * @param int           $entries      only this many rows (0: all)
     * @param int           $skip         the first rows skipped
     * @param int           $batch        flushed every this many entities
     * @param bool          $notify       the notifications the import raises sent (the notifier is disabled otherwise)
     * @param bool          $force        imported without confirmation
     * @param bool          $onTheFly     each entity imported as soon as it is hydrated
     */
    public function __construct(
        public ?string $path = null,
        public ?array $spreadsheets = null,
        public ?string $extension = null,
        public int $entries = 0,
        public int $skip = 0,
        public int $batch = 25,
        public bool $notify = false,
        public bool $force = false,
        public bool $onTheFly = false,
    ) {
    }
}
