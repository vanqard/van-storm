<?php

declare(strict_types=1);

namespace Vanqard\Test\Integration;

use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Test\Fixtures\Membership;

final class CompositeKeyTest extends DatabaseTestCase
{
    public function testCompositeKeyRoundTrip(): void
    {
        $mapper = $this->locator->mapper(Membership::class);

        $membership = new Membership();
        $membership->userId = 1;
        $membership->groupId = 2;
        $mapper->addNew($membership);

        $found = $mapper->find(['userId' => 1, 'groupId' => 2]);
        self::assertNotNull($found);
        self::assertSame('member', $found->role);

        $found->role = 'owner';
        $mapper->save($found);
        self::assertSame('owner', $mapper->find(['userId' => 1, 'groupId' => 2])?->role);

        self::assertTrue($mapper->remove($found));
        self::assertNull($mapper->find(['userId' => 1, 'groupId' => 2]));
    }

    public function testScalarFindIsRejectedForCompositeKeys(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->locator->mapper(Membership::class)->find(1);
    }
}
