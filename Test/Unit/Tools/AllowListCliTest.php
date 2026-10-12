<?php

declare(strict_types=1);

namespace Vanqard\Test\Unit\Tools;

use PHPUnit\Framework\TestCase;

final class AllowListCliTest extends TestCase
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

    /**
     * @param list<string> $args
     * @return array{int, string, string} exit code, stdout, stderr
     */
    private function runCli(array $args): array
    {
        $command = [PHP_BINARY, dirname(__DIR__, 3) . '/Scripts/storm-allowlist', ...$args];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->tmp);
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    public function testWritesTheAllowList(): void
    {
        unlink($this->tmp . '/Model/Broken.php');

        [$code, $stdout] = $this->runCli([
            '--output=allowed.php', '--exclude=vendor', '--include=\\DateTimeImmutable', 'Model',
        ]);

        self::assertSame(0, $code);
        self::assertStringContainsString('Wrote 8 classes from 6 files to allowed.php', $stdout);

        $classes = require $this->tmp . '/allowed.php';
        self::assertIsArray($classes);
        self::assertContains('DateTimeImmutable', $classes);
        self::assertContains('App\\Model\\User', $classes);
        self::assertNotContains('Vendor\\Dependency', $classes);
    }

    public function testParseErrorsGiveExitCodeTwoButStillWrite(): void
    {
        [$code, , $stderr] = $this->runCli(['--output=allowed.php', 'Model']);

        self::assertSame(2, $code);
        self::assertStringContainsString('Broken.php', $stderr);
        self::assertFileExists($this->tmp . '/allowed.php');
    }

    public function testDryRunPrintsInsteadOfWriting(): void
    {
        [, $stdout] = $this->runCli(['--dry-run', 'Model/Status.php']);

        self::assertStringContainsString("'App\\\\Model\\\\Status',", $stdout);
        self::assertFileDoesNotExist($this->tmp . '/allowed.php');
    }

    public function testUsageErrors(): void
    {
        self::assertSame(1, $this->runCli(['Model'])[0]);
        self::assertSame(1, $this->runCli(['--output=allowed.php'])[0]);
        self::assertSame(1, $this->runCli(['--output=allowed.php', 'missing-dir'])[0]);
        self::assertSame(1, $this->runCli(['--output=allowed.php', '--include=not a class', 'Model'])[0]);
        self::assertSame(1, $this->runCli(['--output=no-such-dir/allowed.php', 'Model/Status.php'])[0]);
        self::assertSame(0, $this->runCli(['--help'])[0]);
    }
}
