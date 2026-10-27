---
title: Errors
order: 50
---

# Errors

The package has no exception class of its own: it throws Doctrine's, as Doctrine would.

| Exception | Thrown by | When |
|---|---|---|
| `Doctrine\ORM\Mapping\MappingException` | `DeclarationDriver::loadMetadataForClass()` | a declaration that is no entity, mapped superclass nor embeddable (`classIsNotAValidEntityOrMappedSuperClass`); an index or unique constraint with both or neither of columns and fields; an attribute invalid on an embeddable; an entity listener class not found; an unknown fetch mode or generated mode - the same factories as Doctrine's attribute driver |
| `Error` | `DeclarationDriver` | an attribute class of the declaration that doctrine/orm does not have (a declaration written for another version): `new $className(...)` fails |
| `Doctrine\DBAL\Types\Exception\InvalidFormat` (a `ConversionException`) | `Type\DateTimeType::convertToPHPValue()` | a stored value that is not in the platform's date-time format |
| `Doctrine\DBAL\Types\ConversionException` | `UtcDateTimeType`, `UtcDateTimeImmutableType` | a value that neither the platform's format nor `date_create()` reads, through DBAL's own conversion |
| `\LengthException` | `NamingStrategy::classToTableName()` (`CamelCaseNaming`) | a table name longer than 64 characters |
| `\OutOfBoundsException` | the `Description` returned by `MetadataDescription::of()` | a class or table asked that it does not hold |
