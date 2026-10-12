<?php

declare(strict_types=1);

namespace Vanqard\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Storm\Types\DateTimeType;

final class DateTimeTypeTest extends TestCase
{
    public function testSetters(): void
    {
        $date = (new DateTimeType('2020-01-15 10:20:30'))
            ->setYear(2026)
            ->setMonth(2)
            ->setDay(3)
            ->setHour(4)
            ->setMinutes(5)
            ->setSeconds(6);

        self::assertSame('2026-02-03 04:05:06', $date->format('Y-m-d H:i:s'));
    }

    public function testSerialization(): void
    {
        $date = new DateTimeType('2026-02-03 04:05:06', new \DateTimeZone('UTC'));
        self::assertSame('"2026-02-03T04:05:06+00:00"', json_encode($date));
        self::assertSame('2026-02-03T04:05:06+00:00', (string) $date);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function outOfRange(): iterable
    {
        yield 'day low' => ['setDay', 0];
        yield 'day high' => ['setDay', 32];
        yield 'month low' => ['setMonth', 0];
        yield 'month high' => ['setMonth', 13];
        yield 'year low' => ['setYear', 0];
        yield 'year high' => ['setYear', 10000];
        yield 'hour' => ['setHour', 24];
        yield 'minute' => ['setMinutes', -1];
        yield 'second' => ['setSeconds', 60];
    }

    #[DataProvider('outOfRange')]
    public function testOutOfRangeValuesAreRejected(string $method, int $value): void
    {
        $this->expectException(InvalidParameterException::class);
        (new DateTimeType())->$method($value);
    }
}
