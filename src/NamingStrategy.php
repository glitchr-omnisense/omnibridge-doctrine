<?php

namespace Omnibridge\Doctrine;

use Omnibridge\Schema\CamelCaseNaming;
use Omnibridge\Schema\Naming;

/**
 * Doctrine's naming strategy answered by the family's Naming
 * (Omnibridge\Schema\Naming, omnisense/omnibridge): the names omnibase gives
 * its tables and columns, for any framework mapping the same database -
 * App\Entity\Thread\Tag is threadTag, App\Entity\User\Profile userProfile,
 * Thread::$tags the join table thread_tags (thread_id, threadTag_id), a
 * property its own name. One place says how a class becomes a table
 * (CamelCaseNaming): omnibase's Base\Database\Mapping\NamingStrategy extends
 * this class, and the description built without the ORM (Omnibridge\Schema\
 * Builder) asks the same Naming, so that both find the same tables.
 */
class NamingStrategy implements \Doctrine\ORM\Mapping\NamingStrategy
{
    public const TABLE_NAME_SIZE = CamelCaseNaming::TABLE_NAME_SIZE;
    public const TABLE_I18N_SUFFIX = CamelCaseNaming::TABLE_I18N_SUFFIX;

    public function __construct(private readonly Naming $naming = new CamelCaseNaming())
    {
    }

    public function naming(): Naming
    {
        return $this->naming;
    }

    public function classToTableName(string $className): string
    {
        return $this->naming->classToTableName($className);
    }

    public function propertyToColumnName(string $propertyName, string $className): string
    {
        return $this->naming->propertyToColumnName($propertyName, $className);
    }

    public function embeddedFieldToColumnName(string $propertyName, string $embeddedColumnName, string $className, string $embeddedClassName): string
    {
        return $this->naming->embeddedFieldToColumnName($propertyName, $embeddedColumnName, $className, $embeddedClassName);
    }

    public function referenceColumnName(): string
    {
        return $this->naming->referenceColumnName();
    }

    public function joinColumnName(string $propertyName, string $className): string
    {
        return $this->naming->joinColumnName($propertyName, $className);
    }

    public function joinTableName(string $sourceEntity, string $targetEntity, string $propertyName): string
    {
        return $this->naming->joinTableName($sourceEntity, $targetEntity, $propertyName);
    }

    public function joinKeyColumnName(string $entityName, ?string $referencedColumnName): string
    {
        return $this->naming->joinKeyColumnName($entityName, $referencedColumnName);
    }
}
