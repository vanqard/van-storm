<?php

declare(strict_types=1);

namespace Vanqard\Test\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Storm\Tools\ClassScanner;

final class ClassScannerTest extends TestCase
{
    use TempDirectoryTrait;

    protected function setUp(): void
    {
        $this->makeTempDirectory();
        $this->writeFiles(self::sourceTree());
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    public function testFindsOnlyConcreteClassesAndEnums(): void
    {
        $result = (new ClassScanner())->scan([$this->tmp . '/Model'], ['vendor']);

        self::assertSame([
            'App\\Four\\Fourth',
            'App\\Model\\Status',
            'App\\Model\\User',
            'App\\One\\First',
            'App\\Three\\Third',
            'App\\Two\\Second',
            'GlobalThing',
        ], $result->classes);
        self::assertSame(7, $result->filesScanned);
    }

    public function testParseErrorsAreReportedNotFatal(): void
    {
        $result = (new ClassScanner())->scan([$this->tmp . '/Model'], ['vendor']);

        self::assertCount(1, $result->errors);
        self::assertStringEndsWith('Broken.php', (string) array_key_first($result->errors));
    }

    public function testExcludedDirectoriesCanBeGivenByPath(): void
    {
        $withVendor = (new ClassScanner())->scan([$this->tmp . '/Model']);
        self::assertContains('Vendor\\Dependency', $withVendor->classes);

        $withoutVendor = (new ClassScanner())->scan([$this->tmp . '/Model'], [$this->tmp . '/Model/vendor']);
        self::assertNotContains('Vendor\\Dependency', $withoutVendor->classes);
    }

    public function testSingleFilesCanBeScanned(): void
    {
        $result = (new ClassScanner())->scan([$this->tmp . '/Model/Status.php']);
        self::assertSame(['App\\Model\\Status'], $result->classes);
    }

    public function testMissingPathIsRejected(): void
    {
        $this->expectException(InvalidParameterException::class);
        (new ClassScanner())->scan([$this->tmp . '/nope']);
    }

    public function testScanningDoesNotLoadClasses(): void
    {
        (new ClassScanner())->scan([$this->tmp . '/Model'], ['vendor']);
        self::assertFalse(class_exists('App\\Model\\User', false));
    }
}
