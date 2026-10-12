<?php

declare(strict_types=1);

namespace Vanqard\Storm\Types;

use Vanqard\Storm\Interfaces\TypeCastInterface;

class BooleanTypecast implements TypeCastInterface
{
    public function getDatabaseValue(mixed $value = null): ?int
    {
        if ($value === null) {
            return null;
        }

        return ((bool) $value) ? 1 : 0;
    }

    public function getPhpValue(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }
}
