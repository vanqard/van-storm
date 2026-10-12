<?php

declare(strict_types=1);

namespace Vanqard\Storm\Interfaces;

interface SerializerInterface
{
    public function serialize(mixed $value): string;

    public function unserialize(mixed $value): mixed;
}
