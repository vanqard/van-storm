<?php

declare(strict_types=1);

namespace Vanqard\Storm\Types;

use Vanqard\Storm\Interfaces\TypeCastInterface;

class IntegerTypecast implements TypeCastInterface
{
    public function getDatabaseValue(mixed $value = null): ?int
    {
        return $value === null ? null : (int) $value;
    }

    public function getPhpValue(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
