<?php

namespace Omnibridge\Doctrine;

use Doctrine\ORM\Mapping\Table;

/**
 * The names omnibase gives its tables and columns, for any framework mapping
 * the same database: App\Entity\Thread\Tag is threadTag, App\Entity\User\Profile
 * userProfile, Thread::$tags the join table thread_tags (thread_id,
 * threadTag_id), a property its own name. The same rules and results as
 * omnibase's Base\Database\Mapping\NamingStrategy (copied: two frameworks now
 * need them, plan 0 rule 13; omnibase is to take it from here).
 */
class NamingStrategy implements \Doctrine\ORM\Mapping\NamingStrategy
{
    public const TABLE_NAME_SIZE = 64;
    public const TABLE_I18N_SUFFIX = 'Intl';

    public function classToTableName(string $className): string
    {
        if (!$className) {
            return '';
        }

        $tableName = null;
        $className = class_exists($className) ? (new \ReflectionClass($className))->getName() : $className;

        // A #[Table(name: ...)] says it.
        if (class_exists($className)) {
            foreach (array_reverse((new \ReflectionClass($className))->getAttributes(Table::class)) as $attribute) {
                $table = $attribute->newInstance();
                if (!empty($table->name)) {
                    $tableName = $table->name;
                    break;
                }
            }
        }

        if (!$tableName) {
            // App\ or Base\ off, the first Entity\ off, the rest in camel case.
            $tableName = self::lstrip($className, ['App\\', 'Base\\']);
            $pos = strpos($tableName, 'Entity\\');
            if (false !== $pos) {
                $tableName = substr_replace($tableName, '', $pos, \strlen('Entity\\'));
            }
            if ('' === $tableName) {
                $tableName = false !== strrpos($className, '\\') ? lcfirst(substr($className, strrpos($className, '\\') + 1)) : $className;
            }

            $tableName = str_replace('\\', '', $tableName);
            $parts = array_unique(explode('_', self::camel2snake($tableName)));
            $tableName = lcfirst(self::snake2camel(implode('_', $parts)));
            $tableName = preg_replace('/'.self::TABLE_I18N_SUFFIX.'$/', self::TABLE_I18N_SUFFIX, $tableName);
        }

        if (\strlen($tableName) > self::TABLE_NAME_SIZE) {
            throw new \LengthException(\sprintf('Table name will be truncated for "%s"', $className));
        }

        return $tableName;
    }

    public function propertyToColumnName(string $propertyName, string $className): string
    {
        return lcfirst($propertyName);
    }

    public function embeddedFieldToColumnName(string $propertyName, string $embeddedColumnName, string $className, string $embeddedClassName): string
    {
        return lcfirst($propertyName).'_'.lcfirst($embeddedColumnName);
    }

    public function referenceColumnName(): string
    {
        return 'id';
    }

    public function joinColumnName(string $propertyName, string $className): string
    {
        return lcfirst($propertyName).'_'.lcfirst($this->referenceColumnName());
    }

    public function joinTableName(string $sourceEntity, string $targetEntity, string $propertyName): string
    {
        return lcfirst($this->classToTableName($sourceEntity)).'_'.lcfirst($this->classToTableName($propertyName ?: $targetEntity));
    }

    public function joinKeyColumnName(string $entityName, ?string $referencedColumnName): string
    {
        return lcfirst($this->classToTableName($entityName)).'_'.lcfirst($referencedColumnName ?: $this->referenceColumnName());
    }

    /** omnibase's str_lstrip() with a list: every prefix off, again and again. */
    private static function lstrip(string $haystack, array $prefixes): string
    {
        do {
            $last = $haystack;
            foreach ($prefixes as $prefix) {
                while ('' !== $prefix && str_starts_with($haystack, $prefix)) {
                    $haystack = substr($haystack, \strlen($prefix));
                }
            }
        } while ($haystack !== $last);

        return $haystack;
    }

    /** omnibase's camel2snake(). */
    private static function camel2snake(string $input, string $separator = '_'): string
    {
        return mb_strtolower(str_replace($separator.$separator, $separator, str_replace('.'.$separator, '.', preg_replace('/(?<!^)[A-Z]/', $separator.'$0', str_replace(' ', $separator, $input)))));
    }

    /** omnibase's snake2camel(). */
    private static function snake2camel(string $input, string $separator = '_'): string
    {
        $words = array_map(static fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1), explode(' ', str_replace($separator, ' ', $input)));

        return lcfirst(implode('', $words));
    }
}
