<?php

declare(strict_types=1);

namespace Vanqard\Test\Unit;

use PHPUnit\Framework\TestCase;
use Vanqard\Storm\App\SerializerProxy;
use Vanqard\Storm\Exceptions\DisallowedClassException;
use Vanqard\Storm\Exceptions\InvalidSerializerInstanceException;
use Vanqard\Test\Fixtures\Address;
use Vanqard\Test\Fixtures\Membership;

final class SerializerProxyTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/storm-proxy-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testAllowedClassesRoundTrip(): void
    {
        $proxy = new SerializerProxy(null, [Address::class]);
        $address = new Address('A', 'B', new Address('C', 'D'));

        self::assertEquals($address, $proxy->unserialize($proxy->serialize($address)));
        self::assertSame([Address::class], $proxy->getAllowedClasses());
    }

    public function testScalarsAndArraysNeedNoAllowList(): void
    {
        $proxy = new SerializerProxy();
        self::assertSame(['a' => [1, 'x']], $proxy->unserialize($proxy->serialize(['a' => [1, 'x']])));
    }

    public function testDefaultProxyRefusesToReadAnyClass(): void
    {
        $json = SerializerProxy::unrestricted()->serialize(new Address('A', 'B'));

        $this->expectException(DisallowedClassException::class);
        $this->expectExceptionMessage(Address::class);
        (new SerializerProxy())->unserialize($json);
    }

    public function testDefaultProxyRefusesToWriteAnyClass(): void
    {
        $this->expectException(DisallowedClassException::class);
        (new SerializerProxy())->serialize(new Address());
    }

    public function testNestedDisallowedClassIsRejectedOnRead(): void
    {
        $json = SerializerProxy::unrestricted()->serialize(new Address('A', 'B', new Address()));
        $json = str_replace('"@type":"' . addslashes(Address::class) . '","street":""', '"@type":"' . addslashes(Membership::class) . '","street":""', $json);

        $this->expectException(DisallowedClassException::class);
        $this->expectExceptionMessage(Membership::class);
        (new SerializerProxy(null, [Address::class]))->unserialize($json);
    }

    public function testNestedDisallowedClassIsRejectedOnWrite(): void
    {
        $value = new Address('A', 'B');
        $value->previous = new Address();

        $this->expectException(DisallowedClassException::class);
        (new SerializerProxy(null, [\stdClass::class]))->serialize(['outer' => $value]);
    }

    public function testBackReferencesAreAllowed(): void
    {
        $shared = new Address('Shared', 'X');
        $proxy = new SerializerProxy(null, [Address::class]);

        $result = $proxy->unserialize($proxy->serialize([$shared, $shared]));
        self::assertIsArray($result);
        self::assertSame($result[0], $result[1]);
    }

    public function testUnrestrictedAllowsAnything(): void
    {
        $proxy = SerializerProxy::unrestricted();
        self::assertNull($proxy->getAllowedClasses());
        self::assertEquals(new Address('A', 'B'), $proxy->unserialize($proxy->serialize(new Address('A', 'B'))));
    }

    public function testFromAllowListFile(): void
    {
        $file = $this->dir . '/allowed.php';
        file_put_contents($file, "<?php\nreturn ['\\\\" . addslashes(Address::class) . "'];\n");

        $proxy = SerializerProxy::fromAllowListFile($file);
        self::assertSame([Address::class], $proxy->getAllowedClasses());
    }

    public function testFromAllowListFileRejectsMissingFile(): void
    {
        $this->expectException(InvalidSerializerInstanceException::class);
        SerializerProxy::fromAllowListFile($this->dir . '/missing.php');
    }

    public function testFromAllowListFileRejectsMalformedContents(): void
    {
        $file = $this->dir . '/bad.php';
        file_put_contents($file, "<?php\nreturn [42];\n");

        $this->expectException(InvalidSerializerInstanceException::class);
        SerializerProxy::fromAllowListFile($file);
    }

    public function testRejectsObjectsWithoutSerializeMethods(): void
    {
        $this->expectException(InvalidSerializerInstanceException::class);
        new SerializerProxy(new \stdClass());
    }
}
