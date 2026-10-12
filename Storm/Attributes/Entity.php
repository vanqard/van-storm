<?php

declare(strict_types=1);

namespace Vanqard\Storm\Attributes;

use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Storm\Exceptions\InvalidPrimaryKeyDeclarationException;
use Vanqard\Storm\Exceptions\UnidentifiedEntityFieldNameException;

/**
 * Declares the table and (optionally) the mapper class for an entity.
 *
 * Instances of this class also serve as the resolved metadata for an entity
 * once the EntityMetadataFactory has attached its Column definitions.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Entity
{
    /**
     * @var string
     */
    private string $table = '';

    /**
     * @var string
     */
    private string $mapper = '';

    /**
     * @var string
     */
    private string $entityClass = '';

    /**
     * @var Column[]
     */
    private array $columns = [];

    /**
     * @param string $table
     * @param string $mapper
     */
    public function __construct(
        string $table = '',
        string $mapper = '',
        string $entityClass = ''
    ) {
        $this->table = $table;
        $this->mapper = $mapper;
        $this->entityClass = $entityClass;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getMapper(): string
    {
        return $this->mapper;
    }

    /**
     * @return string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @param Column $column
     * @return $this
     */
    public function addColumn(Column $column): Entity
    {
        $propertyName = $column->getFieldName();
        $this->columns[$propertyName] = $column;
        return $this;
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return Column[]
     */
    public function getPrimaryKeyColumns(): array
    {
        $retArray = [];

        foreach ($this->getColumns() as $fieldName => $columnSpec) {
            if ($columnSpec->isPrimary()) {
                $retArray[] = $columnSpec;
            }
        }

        return $retArray;
    }

    /**
     * @return list<string>
     */
    public function getPrimaryKeyColumnsAsList(): array
    {
        $retArray = [];
        foreach ($this->getColumns() as $columnSpec) {
            if ($columnSpec->isPrimary()) {
                $retArray[] = $columnSpec->getName();
            }
        }

        return $retArray;
    }

    /**
     * @return list<string>
     */
    public function getPrimaryKeyFieldsAsList(): array
    {
        $retArray = [];
        foreach ($this->getColumns() as $columnSpec) {
            if ($columnSpec->isPrimary()) {
                $retArray[] = $columnSpec->getFieldName();
            }
        }

        return $retArray;
    }

    /**
     * @return string
     * @throws InvalidPrimaryKeyDeclarationException
     */
    public function getAutoIncrementingPrimaryKeyFieldName(): string
    {
        foreach ($this->getColumns() as $columnSpec) {
            if ($columnSpec->isAutoincrement()) {
                return $columnSpec->getFieldName();
            }
        }

        throw new InvalidPrimaryKeyDeclarationException(
            sprintf("The entity '%s' does not have an auto-incrementing primary key", $this->getEntityClass())
        );
    }

    /**
     * @return bool
     */
    public function hasAutoIncrementingPrimaryKey(): bool
    {
        $retVal = false;

        $primaryKeyColumns = $this->getPrimaryKeyColumns();

        if (count($primaryKeyColumns) == 1) {
            $pk = $primaryKeyColumns[0];
            if ($pk->isAutoincrement()) {
                $retVal = true;
            }
        }

        return $retVal;
    }

    /**
     * @return bool
     * @throws InvalidPrimaryKeyDeclarationException
     */
    public function hasCompositePrimaryKey(): bool
    {
        $primaryKeyColumns = $this->getPrimaryKeyColumns();
        if (count($primaryKeyColumns) <= 1) {
            return false;
        }

        foreach ($primaryKeyColumns as $columnSpec) {
            // None of the primary columns should be auto-incrementing
            if ($columnSpec->isAutoincrement()) {
                throw new InvalidPrimaryKeyDeclarationException(
                    sprintf(
                        "The entity '%s' has been declared with a composite primary key but the field '%s' has the autoincrement flag set",
                        $this->getEntityClass(),
                        $columnSpec->getName()
                    )
                );
            }
        }

        return true;
    }


    /**
     * @param string $columnName
     * @return Column
     * @throws UnidentifiedEntityFieldNameException
     */
    public function getColumnDefByColumnName(string $columnName): Column
    {
        /**
         * @var string $fieldName
         * @var Column $columnSpec
         */
        foreach ($this->columns as $fieldName => $columnSpec) {
            if ($columnSpec->getName() === $columnName) {
                return $columnSpec;
            }
        }

        throw new UnidentifiedEntityFieldNameException(
            sprintf("The column '%s' was not found in the entity definition", $columnName)
        );
    }

    /**
     * @param string $fieldName
     * @return Column
     * @throws UnidentifiedEntityFieldNameException
     */
    public function getColumnDefByFieldName(string $fieldName): Column
    {
        if (array_key_exists($fieldName, $this->columns)) {
            return $this->columns[$fieldName];
        }

        throw new UnidentifiedEntityFieldNameException(
            sprintf("The field '%s' was not found in the entity definition", $fieldName)
        );
    }

    /**
     * @return array<string, string>
     */
    public function getColumnPlaceholdersForSqlStatement(): array
    {
        $retArray = [];

        foreach ($this->columns as $columnSpec) {
            $columnName = $columnSpec->getName();
            $placeHolder = sprintf(":%s", $columnName);
            $retArray[$columnName] = $placeHolder;
        }

        return $retArray;
    }

    /**
     * @param string $columnName
     * @return string
     * @throws InvalidParameterException
     */
    public function getFieldNameForColumnName(string $columnName): string
    {
        foreach ($this->columns as $fieldName => $columnSpec) {
            if ($columnSpec->getName() == $columnName) {
                return $fieldName;
            }
        }

        throw new InvalidParameterException(
            sprintf("Column name '%s' not found in this entity definition", $columnName)
        );
    }

    /**
     * @param string $fieldName
     * @return string
     * @throws InvalidParameterException
     */
    public function getColumnNameForFieldName(string $fieldName): string
    {
        if (array_key_exists($fieldName, $this->columns)) {
            $columnSpec = $this->columns[$fieldName];
            return $columnSpec->getName();
        }

        throw new InvalidParameterException(
            sprintf("The field name '%s' was not found in this entity definition", $fieldName)
        );
    }

    /**
     * @param string $fieldName
     * @return Column
     * @throws InvalidParameterException
     */
    public function getColumnSpecForFieldName(string $fieldName): Column
    {
        if (array_key_exists($fieldName, $this->columns)) {
            return $this->columns[$fieldName];
        }

        throw new InvalidParameterException(
            sprintf("The field name '%s' was not found in this entity definition", $fieldName)
        );
    }

    /**
     * @param string $columnName
     * @return Column
     * @throws InvalidParameterException
     */
    public function getColumnSpecForColumnName(string $columnName): Column
    {
        foreach ($this->columns as $column) {
            if ($column->getName() == $columnName) {
                return $column;
            }
        }

        throw new InvalidParameterException(
            sprintf("The column name '%s' was not found in this entity definition", $columnName)
        );
    }
}
