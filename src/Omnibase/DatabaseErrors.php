<?php

namespace Omnibridge\Doctrine\Omnibase;

use Base\Database\Exception\TableNotFound;
use Base\Database\Exception\UniqueConstraintViolation;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * DBAL's driver exceptions as the core's neutral ones (Base\Database\Exception): a unique constraint refused by a
 * flush, a table the database lacks under a finder - the driver's exception kept as the previous one.
 *
 * @internal
 */
final class DatabaseErrors
{
    /**
     * @template R
     *
     * @param \Closure(): R $work
     *
     * @return R
     */
    public static function reading(\Closure $work): mixed
    {
        try {
            return $work();
        } catch (TableNotFoundException $e) {
            throw TableNotFound::from($e);
        }
    }

    /**
     * @template R
     *
     * @param \Closure(): R $work
     *
     * @return R
     */
    public static function writing(\Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintViolationException $e) {
            throw UniqueConstraintViolation::from($e);
        } catch (TableNotFoundException $e) {
            throw TableNotFound::from($e);
        }
    }
}
