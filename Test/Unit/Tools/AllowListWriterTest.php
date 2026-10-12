<?php

declare(strict_types=1);

namespace Vanqard\Test\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Vanqard\Storm\App\SerializerProxy;
use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Storm\Tools\AllowListWriter;

final class AllowListWriterTest extends TestCase
{
    use TempDirectoryTrait;

    protected function setUp(): void
    {
        $this->makeTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    public function testWritesASortedRequireableList(): void
    {
        $file = $this->tmp . '/allowed.php';
        (new AllowListWriter())->write($file, ['Z\\Last', '\\A\\First', 'A\\First', 'M\\Quote\'s'], ['src']);

        self::assertSame(['A\\First', "M\\Quote's", 'Z\\Last'], require $file);
        self::assertSame(['A\\First', "M\\Quote's", 'Z\\Last'], SerializerProxy::loadAllowListFile($file));
        self::assertStringContainsString('// Scanned: src', (string) file_get_contents($file));
        // No temporary files are left behind
        self::assertSame(['allowed.php'], array_values(array_diff(scandir($this->tmp) ?: [], ['.', '..'])));
    }

    public function testOverwritesAnExistingList(): void
    {
        $file = $this->tmp . '/allowed.php';
        (new AllowListWriter())->write($file, ['Old']);
        (new AllowListWriter())->write($file, ['New']);

        self::assertSame(['New'], require $file);
    }

    public function testMissingDirectoryIsRejected(): void
    {
        $this->expectException(InvalidParameterException::class);
        (new AllowListWriter())->write($this->tmp . '/missing/allowed.php', ['A']);
    }
}
