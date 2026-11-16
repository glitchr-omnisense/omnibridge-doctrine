<?php

namespace Omnibridge\Doctrine\Omnibase;

use Base\Database\Type\ArrayType;
use Base\Database\Type\DateTimeTypeUTC;
use Base\Database\Type\EnumType;
use Base\Database\Type\Enumerations;
use Base\Database\Type\Passkey;
use Base\Database\Type\UtcDateTimeImmutableType;
use Base\Database\Type\UtcDateTimeType;
use Base\Service\Model\ClassFinder;
use Doctrine\DBAL\Types\Type;

/**
 * omnibase's column types, registered with DBAL (plan "omnibase agnostique",
 * C6): what BaseBundle::boot() and the bundle's doctrine.dbal.types did, in
 * one place, for Symfony and for an entity manager built by hand.
 *
 * - `datetime` and `datetimetz` are Base\Database\Type\DateTimeTypeUTC (the
 *   moment kept in UTC whatever zone PHP was put in), `datetime_immutable` and
 *   `datetimetz_immutable` UtcDateTimeImmutableType (the same, immutable), `array` the core's
 *   ArrayType (the serialized arrays of before DBAL 4), `utc_datetime` and
 *   `utc_datetime_immutable` the core's UTC types, `passkey_base64`,
 *   `passkey_aaguid` and `passkey_trust_path` the columns of
 *   Base\Entity\User\Passkey (plan "omniauth", AU1: those of
 *   web-auth/webauthn-symfony-bundle, without it);
 * - every enumeration (Base\Database\Type\EnumType, SetType) of the folders
 *   given, under its getStaticName(), when no type has that name yet: an
 *   EnumTypeAdapter of it (the enumerations are plain PHP classes since step
 *   F2a of the plan "omnibase agnostic"), known to
 *   Base\Database\Type\Enumerations by that name;
 * - the types given by name (a bundle's enumerations), replacing a type of
 *   the same name, as DoctrineBundle registers its configured types.
 */
final class Types
{
    /** @return array<string, class-string<Type>> the core's types, by name */
    public static function core(): array
    {
        return [
            'datetime' => DateTimeTypeUTC::class,
            'datetimetz' => DateTimeTypeUTC::class,
            // One time, UTC: the immutable moments too (Doctrine's own write the wall clock the object carries).
            'datetime_immutable' => UtcDateTimeImmutableType::class,
            'datetimetz_immutable' => UtcDateTimeImmutableType::class,
            'array' => ArrayType::class,
            UtcDateTimeImmutableType::NAME => UtcDateTimeImmutableType::class,
            UtcDateTimeType::NAME => UtcDateTimeType::class,
        ] + self::passkey();
    }

    /** @return array<string, class-string<Type>> the columns of a passkey (Base\Entity\User\Passkey), by name */
    public static function passkey(): array
    {
        return [
            \Base\Database\Type\Passkey\Base64::NAME => \Base\Database\Type\Passkey\Base64::class,
            \Base\Database\Type\Passkey\Aaguid::NAME => \Base\Database\Type\Passkey\Aaguid::class,
            \Base\Database\Type\Passkey\TrustPath::NAME => \Base\Database\Type\Passkey\TrustPath::class,
        ];
    }

    /** @return array<string, class-string<EnumType>> the enumerations the folders hold, by their names */
    public static function enumerations(array $directories): array
    {
        $types = [];
        // App\Enum\X is the application's own where it declares one (extending Base\Enum\X with its cases), the core's otherwise.
        \Base\Service\Model\ClassAliases::register();
        foreach ($directories as $directory) {
            foreach (ClassFinder::classes($directory) as $className) {
                if (str_starts_with($className, 'Base\\Enum\\')) {
                    $className = (new \ReflectionClass('App\\'.substr($className, \strlen('Base\\'))))->getName();
                }
                // A plain PHP enum (Base\Enum\Allergen, CommentState) is no Doctrine type.
                if (!class_exists($className) || !is_subclass_of($className, EnumType::class) || (new \ReflectionClass($className))->isAbstract()) {
                    continue;
                }

                $types[$className::getStaticName()] ??= $className;
            }
        }

        return $types;
    }

    /**
     * @param array<string, class-string<Type>> $types the types to register by name, over any of that name
     * @param list<string> $enumerationDirectories the folders whose enumerations are registered, under names not taken
     */
    public static function register(array $types = [], array $enumerationDirectories = []): void
    {
        $enumerations = [];
        foreach (self::core() + $types as $name => $className) {
            // A bundle's enumerations given by name (doctrine.dbal.types of before) replace a type as a type does.
            if (is_subclass_of($className, EnumType::class)) {
                $enumerations[$name] = $className;
                continue;
            }
            Type::hasType($name) ? Type::overrideType($name, $className) : Type::addType($name, $className);
        }
        self::registerEnumerations($enumerations, true);

        self::registerEnumerations(self::enumerations($enumerationDirectories));
    }

    /**
     * Each enumeration as an EnumTypeAdapter under its name, and in Base\Database\Type\Enumerations.
     *
     * @param array<string, class-string<EnumType>> $enumerations by name
     * @param bool $override replace a type of that name (else a name taken is left as it is)
     */
    public static function registerEnumerations(array $enumerations, bool $override = false): void
    {
        $registry = Type::getTypeRegistry();
        foreach ($enumerations as $name => $className) {
            if ($registry->has($name)) {
                $current = $registry->get($name);
                if (!$override || ($current instanceof EnumTypeAdapter && $current->enumeration() === $className)) {
                    if ($current instanceof EnumTypeAdapter) {
                        Enumerations::register($name, $current->enumeration());
                    }
                    continue;
                }
                $registry->override($name, new EnumTypeAdapter($className));
            } else {
                $registry->register($name, new EnumTypeAdapter($className));
            }
            Enumerations::register($name, $className);
        }
    }

    /** The core's own enumerations (src/Enum). */
    public static function coreEnumerationDirectory(): string
    {
        return dirname((new \ReflectionClass(EnumType::class))->getFileName(), 3).'/Enum';
    }
}
