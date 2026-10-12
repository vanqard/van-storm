<?php

declare(strict_types=1);

namespace Vanqard\Storm\Attributes;

/**
 * Maps an entity property to a database column.
 *
 * `type` selects the typecast from the TypeRegistry (e.g. 'int', 'string',
 * 'bool', 'datetime', 'object', 'json'). An empty type falls back to Storm's
 * naming conventions. An empty `name` defaults to the snake_case form of the
 * property name.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * @var string
     */
    private string $type;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var bool
     */
    private bool $nullable;

    /**
     * @var bool
     */
    private bool $primary = false;

    /**
     * @var bool
     */
    private bool $autoincrement = false;

    /**
     * @var string
     */
    private string $fieldName = '';

    /**
     * @param string $type
     * @param string $name
     * @param bool $nullable
     * @param bool $primary
     * @param bool $autoincrement
     */
    public function __construct(
        string $type = '',
        string $name = '',
        bool $nullable = false,
        bool $primary = false,
        bool $autoincrement = false
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->nullable = $nullable;
        $this->primary = $primary;
        $this->autoincrement = $autoincrement;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Column
     */
    public function setName(string $name): Column
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @return bool
     */
    public function isPrimary(): bool
    {
        return $this->primary;
    }

    /**
     * @return bool
     */
    public function isAutoincrement(): bool
    {
        return $this->autoincrement;
    }

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * @param string $fieldName
     * @return Column
     */
    public function setFieldName(string $fieldName): Column
    {
        $this->fieldName = $fieldName;
        return $this;
    }
}
