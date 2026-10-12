<?php

declare(strict_types=1);

namespace Vanqard\Storm\Types;

use DateTimeInterface;
use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Storm\Interfaces\TypeCastInterface;

class DateTimeTypecast implements TypeCastInterface
{
    public function getDatabaseValue(mixed $value = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeType::DATABASE_FORMAT);
        }

        if (is_string($value)) {
            return $value;
        }

        throw new InvalidParameterException(
            sprintf("Cannot convert a value of type '%s' to a datetime", get_debug_type($value))
        );
    }

    /**
     * @throws InvalidParameterException when the stored value cannot be parsed
     */
    public function getPhpValue(mixed $value): ?DateTimeType
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return new DateTimeType($value->format('Y-m-d H:i:s.u'), $value->getTimezone());
        }

        try {
            return new DateTimeType((string) $value);
        } catch (\Exception $e) {
            throw new InvalidParameterException(
                sprintf("Unable to parse '%s' as a datetime", (string) $value),
                0,
                $e
            );
        }
    }
}
