<?php

declare(strict_types=1);

namespace Vanqard\Storm\Exceptions;

/**
 * Thrown when serialized data names a class that is not on the configured
 * allow-list. Generate the list with `vendor/bin/storm-allowlist`.
 */
class DisallowedClassException extends \Exception implements StormExceptionInterface
{
    public static function forClass(string $className, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf(
                "The class '%s' is not on the allow-list for object deserialization. "
                . "Add it with `vendor/bin/storm-allowlist` (or the allowedClasses option) if it is safe to rehydrate.",
                $className
            ),
            0,
            $previous
        );
    }
}
