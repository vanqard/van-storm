<?php

declare(strict_types=1);

namespace Vanqard\Storm\Tools;

use FilesystemIterator;
use PhpToken;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Vanqard\Storm\Exceptions\InvalidParameterException;

/**
 * Finds the concrete classes and enums declared in PHP source files, for use
 * as a deserialization allow-list.
 *
 * Files are tokenized, never included or autoloaded, so scanning has no side
 * effects. Abstract classes, interfaces, traits and anonymous classes are
 * skipped because they can never be the class of a serialized object.
 */
final class ClassScanner
{
    /**
     * @param list<string> $paths Files or directories to scan (directories recursively)
     * @param list<string> $excludeDirs Directory names (e.g. 'vendor') or paths to skip
     * @throws InvalidParameterException when a path does not exist
     */
    public function scan(array $paths, array $excludeDirs = []): ScanResult
    {
        $classes = [];
        $errors = [];
        $count = 0;

        // Match each exclusion both as a bare directory name and as a resolved path
        $excludePaths = [];
        foreach ($excludeDirs as $exclude) {
            $excludePaths[] = rtrim($exclude, '/\\');
            $real = realpath($exclude);
            if ($real !== false) {
                $excludePaths[] = $real;
            }
        }

        foreach ($paths as $path) {
            foreach ($this->phpFiles($path, $excludePaths) as $file) {
                $count++;
                $code = file_get_contents($file);
                if ($code === false) {
                    $errors[$file] = 'Unable to read file';
                    continue;
                }

                try {
                    array_push($classes, ...$this->classesIn($code));
                } catch (\ParseError $e) {
                    $errors[$file] = sprintf('%s on line %d', $e->getMessage(), $e->getLine());
                }
            }
        }

        $classes = array_values(array_unique($classes));
        sort($classes, SORT_STRING);
        ksort($errors, SORT_STRING);

        return new ScanResult($classes, $errors, $count);
    }

    /**
     * Returns the fully qualified names of concrete classes and enums declared
     * in a piece of PHP code.
     *
     * @return list<string>
     * @throws \ParseError
     */
    public function classesIn(string $code): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($code, TOKEN_PARSE),
            static fn (PhpToken $t): bool => !$t->isIgnorable()
        ));

        $namespace = '';
        $found = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->is(T_NAMESPACE)) {
                $next = $tokens[$i + 1] ?? null;
                // `namespace\Foo` is a relative name, not a declaration
                if ($next !== null && $next->is([T_STRING, T_NAME_QUALIFIED])) {
                    $namespace = $next->text;
                    $i++;
                } elseif ($next !== null && $next->text === '{') {
                    $namespace = '';
                }
                continue;
            }

            if (!$token->is([T_CLASS, T_ENUM])) {
                continue;
            }

            $previous = $tokens[$i - 1] ?? null;
            // Foo::class constant or `new class` anonymous class
            if ($previous !== null && $previous->is([T_DOUBLE_COLON, T_NEW])) {
                continue;
            }

            $name = $tokens[$i + 1] ?? null;
            if ($name === null || !$name->is(T_STRING)) {
                continue;
            }

            if ($token->is(T_CLASS) && $this->isAbstract($tokens, $i)) {
                continue;
            }

            $found[] = $namespace === '' ? $name->text : $namespace . '\\' . $name->text;
        }

        return $found;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function isAbstract(array $tokens, int $classIndex): bool
    {
        // Walk back over modifiers: final, readonly, abstract
        for ($j = $classIndex - 1; $j >= 0; $j--) {
            $t = $tokens[$j];
            if ($t->is(T_ABSTRACT)) {
                return true;
            }
            if (!$t->is([T_FINAL, T_READONLY])) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param list<string> $excludePaths
     * @return iterable<string>
     */
    private function phpFiles(string $path, array $excludePaths): iterable
    {
        if (is_file($path)) {
            yield $path;
            return;
        }

        if (!is_dir($path)) {
            throw new InvalidParameterException(sprintf("The path '%s' does not exist", $path));
        }

        $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $file) use ($excludePaths): bool {
                if ($file->isLink()) {
                    return false;
                }

                if ($file->isDir()) {
                    $real = $file->getRealPath();
                    foreach ($excludePaths as $exclude) {
                        if ($file->getFilename() === $exclude || $real === $exclude) {
                            return false;
                        }
                    }
                    return true;
                }

                return strtolower($file->getExtension()) === 'php';
            }
        );

        $files = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator($filter) as $file) {
            $files[] = $file->getPathname();
        }
        sort($files, SORT_STRING);

        yield from $files;
    }
}
