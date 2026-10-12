<?php

declare(strict_types=1);

namespace Vanqard\Storm\App;

use JsonSerializable;
use Vanqard\Storm\Types\DateTimeType;

/**
 * Base class for entities.
 *
 * Describe the table with an #[Entity] attribute and each persisted property
 * with a #[Column] attribute, or set the static $table, $mapper and
 * $primaryKey properties and let Storm map every property by convention.
 */
abstract class AbstractEntity implements JsonSerializable
{
    public static string $primaryKey = 'id';

    public static string $table = '';

    public static string $mapper = '';

    /**
     * Populates accessible properties from an array keyed by property name.
     *
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): static
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = str_ends_with($key, 'At') && is_string($value)
                    ? new DateTimeType($value)
                    : $value;
            }
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function getPrimaryKeyName(): string
    {
        return static::$primaryKey;
    }

    public static function getTableName(): string
    {
        return static::$table;
    }

    public static function getMapper(): string
    {
        return static::$mapper;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
