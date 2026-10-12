<?php

declare(strict_types=1);

namespace Vanqard\Storm\Metadata;

use ReflectionClass;
use ReflectionProperty;
use Vanqard\Storm\App\AbstractEntity;
use Vanqard\Storm\Attributes\Column;
use Vanqard\Storm\Attributes\Entity;
use Vanqard\Storm\Exceptions\InvalidEntityClassDeclarationException;
use Vanqard\Storm\Exceptions\InvalidPrimaryKeyDeclarationException;

/**
 * Builds (and caches) the resolved Entity definition for an entity class.
 *
 * Entities declared with #[Entity]/#[Column] attributes are described by
 * those attributes alone. Entities without any #[Column] attributes fall back
 * to naming conventions: the static $table/$mapper/$primaryKey properties,
 * every non-static property mapped to its snake_case column, and values
 * converted by naming convention (an empty column type).
 */
class EntityMetadataFactory
{
    /**
     * @var array<class-string, Entity>
     */
    private array $cache = [];

    /**
     * @param class-string $entityClass
     * @throws InvalidEntityClassDeclarationException
     * @throws InvalidPrimaryKeyDeclarationException
     */
    public function getMetadata(string $entityClass): Entity
    {
        return $this->cache[$entityClass] ??= $this->build($entityClass);
    }

    /**
     * @param class-string $entityClass
     */
    private function build(string $entityClass): Entity
    {
        if (!class_exists($entityClass) || !is_subclass_of($entityClass, AbstractEntity::class)) {
            throw new InvalidEntityClassDeclarationException(
                sprintf("'%s' is not a subclass of %s", $entityClass, AbstractEntity::class)
            );
        }

        $reflection = new ReflectionClass($entityClass);

        $entityAttributes = $reflection->getAttributes(Entity::class);
        $declared = $entityAttributes === [] ? null : $entityAttributes[0]->newInstance();

        $table = $declared?->getTable() ?: $entityClass::getTableName();
        $mapper = $declared?->getMapper() ?: $entityClass::getMapper();

        if ($table === '') {
            throw new InvalidEntityClassDeclarationException(
                sprintf("The entity '%s' does not declare a table name", $entityClass)
            );
        }

        $metadata = new Entity($table, $mapper, $entityClass);

        $properties = array_filter(
            $reflection->getProperties(),
            static fn (ReflectionProperty $p): bool => !$p->isStatic()
        );

        $columns = $this->columnsFromAttributes($properties);

        if ($columns === []) {
            $columns = $this->columnsFromConventions($properties, $entityClass::$primaryKey);
        }

        foreach ($columns as $column) {
            $metadata->addColumn($column);
        }

        if ($metadata->getPrimaryKeyColumns() === []) {
            throw new InvalidPrimaryKeyDeclarationException(
                sprintf("The entity '%s' does not declare a primary key column", $entityClass)
            );
        }

        // Validates that composite keys are not auto-incrementing
        $metadata->hasCompositePrimaryKey();

        return $metadata;
    }

    /**
     * @param ReflectionProperty[] $properties
     * @return Column[]
     */
    private function columnsFromAttributes(array $properties): array
    {
        $columns = [];

        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Column::class);
            if ($attributes === []) {
                continue;
            }

            /** @var Column $column */
            $column = $attributes[0]->newInstance();
            $column->setFieldName($property->getName());

            if ($column->getName() === '') {
                $column->setName(self::toColumnName($property->getName()));
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @param ReflectionProperty[] $properties
     * @return Column[]
     */
    private function columnsFromConventions(array $properties, string $primaryKey): array
    {
        $columns = [];

        foreach ($properties as $property) {
            $isPrimary = $property->getName() === $primaryKey;
            $column = new Column(
                type: '',
                name: self::toColumnName($property->getName()),
                nullable: true,
                primary: $isPrimary,
                autoincrement: $isPrimary,
            );
            $column->setFieldName($property->getName());
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Converts a camelCase property name to its snake_case column name.
     */
    public static function toColumnName(string $fieldName): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $fieldName));
    }

    /**
     * Converts a snake_case column name to its camelCase property name.
     */
    public static function toFieldName(string $columnName): string
    {
        return lcfirst(str_replace('_', '', ucwords(strtolower($columnName), '_')));
    }
}
