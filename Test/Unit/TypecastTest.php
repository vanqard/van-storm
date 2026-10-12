<?php

declare(strict_types=1);

namespace Vanqard\Test\Unit;

use PHPUnit\Framework\TestCase;
use Vanqard\Storm\App\SerializerProxy;
use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Storm\Interfaces\TypeCastInterface;
use Vanqard\Storm\Types\BooleanTypecast;
use Vanqard\Storm\Types\DateTimeType;
use Vanqard\Storm\Types\DateTimeTypecast;
use Vanqard\Storm\Types\FloatTypecast;
use Vanqard\Storm\Types\IntegerTypecast;
use Vanqard\Storm\Types\JsonTypecast;
use Vanqard\Storm\Types\ObjectTypecast;
use Vanqard\Storm\Types\StringTypecast;
use Vanqard\Storm\Types\TypeRegistry;
use Vanqard\Test\Fixtures\Address;

final class TypecastTest extends TestCase
{
    /**
     * @return iterable<string, array{TypeCastInterface}>
     */
    public static function allTypecasts(): iterable
    {
        yield 'bool' => [new BooleanTypecast()];
        yield 'int' => [new IntegerTypecast()];
        yield 'float' => [new FloatTypecast()];
        yield 'string' => [new StringTypecast()];
        yield 'datetime' => [new DateTimeTypecast()];
        yield 'object' => [new ObjectTypecast()];
        yield 'json' => [new JsonTypecast()];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('allTypecasts')]
    public function testNullPassesThroughUnchanged(TypeCastInterface $typecast): void
    {
        self::assertNull($typecast->getDatabaseValue(null));
        self::assertNull($typecast->getPhpValue(null));
    }

    public function testScalarCasts(): void
    {
        self::assertSame(1, (new BooleanTypecast())->getDatabaseValue(true));
        self::assertSame(0, (new BooleanTypecast())->getDatabaseValue(false));
        self::assertFalse((new BooleanTypecast())->getPhpValue('0'));
        self::assertSame(42, (new IntegerTypecast())->getPhpValue('42'));
        self::assertSame(1.5, (new FloatTypecast())->getPhpValue('1.5'));
        self::assertSame('7', (new StringTypecast())->getPhpValue(7));
    }

    public function testStringRejectsNonStringableValues(): void
    {
        $this->expectException(InvalidParameterException::class);
        (new StringTypecast())->getDatabaseValue(new \stdClass());
    }

    public function testDateTime(): void
    {
        $cast = new DateTimeTypecast();
        self::assertSame('2026-03-04 05:06:07', $cast->getDatabaseValue(new \DateTimeImmutable('2026-03-04 05:06:07')));
        $php = $cast->getPhpValue('2026-03-04 05:06:07');
        self::assertInstanceOf(DateTimeType::class, $php);
        self::assertSame('2026-03-04T05:06:07', $php->format('Y-m-d\TH:i:s'));
    }

    public function testDateTimeRejectsUnparseableValues(): void
    {
        $this->expectException(InvalidParameterException::class);
        (new DateTimeTypecast())->getPhpValue('not a date');
    }

    public function testObjectRoundTripPreservesClasses(): void
    {
        $cast = new ObjectTypecast(new SerializerProxy(null, [Address::class]));
        $address = new Address('High St', 'York', new Address('Low St', 'Hull'));

        $stored = $cast->getDatabaseValue($address);
        self::assertIsString($stored);
        self::assertStringContainsString('@type', $stored);
        self::assertEquals($address, $cast->getPhpValue($stored));
    }

    public function testJsonRoundTrip(): void
    {
        $cast = new JsonTypecast();
        self::assertSame(['a' => 1, 'b' => [true]], $cast->getPhpValue($cast->getDatabaseValue(['a' => 1, 'b' => [true]])));
    }

    public function testRegistryResolvesBuiltInsAndCustomTypes(): void
    {
        $registry = new TypeRegistry();
        self::assertInstanceOf(IntegerTypecast::class, $registry->get('INT'));
        self::assertInstanceOf(BooleanTypecast::class, $registry->get('boolean'));
        self::assertFalse($registry->has('money'));

        $money = new FloatTypecast();
        $registry->register('Money', $money);
        self::assertSame($money, $registry->get('money'));
    }

    public function testRegistryRejectsUnknownTypes(): void
    {
        $this->expectException(InvalidParameterException::class);
        (new TypeRegistry())->get('nope');
    }
}
