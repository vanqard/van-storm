<?php

declare(strict_types=1);

namespace Vanqard\Test\Fixtures;

/**
 * Plain value object stored in an 'object' column.
 */
class Address
{
    public function __construct(
        public string $street = '',
        public string $city = '',
        public ?Address $previous = null,
    ) {
    }
}
