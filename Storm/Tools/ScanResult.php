<?php

declare(strict_types=1);

namespace Vanqard\Storm\Tools;

/**
 * Outcome of a ClassScanner run.
 */
final class ScanResult
{
    /**
     * @param list<string> $classes Fully qualified class and enum names, sorted
     * @param array<string, string> $errors Parse errors keyed by file path
     * @param int $filesScanned Number of PHP files read
     */
    public function __construct(
        public readonly array $classes,
        public readonly array $errors,
        public readonly int $filesScanned,
    ) {
    }
}
