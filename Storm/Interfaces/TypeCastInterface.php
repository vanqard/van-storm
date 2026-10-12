<?php

declare(strict_types=1);

namespace Vanqard\Storm\Interfaces;

/**
 * Converts a single column value between its PHP and database representations.
 * Implementations must pass null through unchanged in both directions.
 */
interface TypeCastInterface
{
    public function getDatabaseValue(mixed $value = null): mixed;

    public function getPhpValue(mixed $value): mixed;
}
