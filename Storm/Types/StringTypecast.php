<?php

declare(strict_types=1);

namespace Vanqard\Storm\Types;

use Stringable;
use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Storm\Interfaces\TypeCastInterface;

class StringTypecast implements TypeCastInterface
{
    public function getDatabaseValue(mixed $value = null): ?string
    {
        return $this->toString($value);
    }

    public function getPhpValue(mixed $value): ?string
    {
        return $this->toString($value);
    }

    private function toString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidParameterException(
            sprintf("Cannot convert a value of type '%s' to a string", get_debug_type($value))
        );
    }
}
