<?php

declare(strict_types=1);

namespace Vanqard\Storm\Types;

use Vanqard\Storm\Interfaces\TypeCastInterface;

/**
 * Stores arrays/scalars as plain JSON and decodes them to associative arrays.
 * Use the 'object' type instead when class information must be preserved.
 */
class JsonTypecast implements TypeCastInterface
{
    public function getDatabaseValue(mixed $value = null): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function getPhpValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }
}
