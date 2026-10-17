<?php

namespace Omnibridge\Doctrine\Type;

/** The zone moments are kept in. */
final class Utc
{
    private static ?\DateTimeZone $zone = null;

    public static function zone(): \DateTimeZone
    {
        return self::$zone ??= new \DateTimeZone('UTC');
    }
}
