<?php

declare(strict_types=1);

namespace Vanqard\Storm\Types;

use Vanqard\Storm\Interfaces\TypeCastInterface;

class FloatTypecast implements TypeCastInterface
{
    public function getDatabaseValue(mixed $value = null): ?float
    {
        return $value === null ? null : (float) $value;
    }

    public function getPhpValue(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
