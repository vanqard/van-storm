<?php

declare(strict_types=1);

namespace Vanqard\Test\Integration;

use Vanqard\Test\Fixtures\Membership;
use Vanqard\Test\Fixtures\User;

/**
 * Mirrors the examples in Docs/query-builder.md.
 */
final class QueryBuilderTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $users = $this->locator->mapper(User::class);
        foreach (['ada' => true, 'grace' => true, 'alan' => false, 'edsger' => true] as $name => $active) {
            $user = new User();
            $user->username = $name;
            $user->isActive = $active;
            $users->addNew($user);
        }
    }

    public function testExecuteRunsInsertUpdateAndDelete(): void
    {
        $users = $this->locator->mapper(User::class);

        $insert = $users->newInsert()->cols([
            'username' => 'barbara',
            'is_active' => 1,
            'created_at' => '2026-01-01 09:00:00',
        ]);
        self::assertSame(1, $users->execute($insert));

        $update = $users->newUpdate()
            ->cols(['is_active' => 0])
            ->where('username IN (:names)', ['names' => ['ada', 'grace']]);
        self::assertSame(2, $users->execute($update));

        $delete = $users->newDelete()->where('is_active = :active', ['active' => 0]);
        self::assertSame(3, $users->execute($delete));

        self::assertSame(2, $users->count());
    }

    public function testSelectIntoEntities(): void
    {
        $users = $this->locator->mapper(User::class);

        $select = $users->newSelect()
            ->cols(['*'])
            ->where('is_active = :active', ['active' => 1])
            ->where('username IN (:names)', ['names' => ['ada', 'grace', 'alan']])
            ->orderBy(['username DESC'])
            ->limit(10);

        $found = $users->findBySelect($select);

        self::assertSame(['grace', 'ada'], array_map(fn (User $u): string => $u->username, $found));
    }

    public function testPaging(): void
    {
        $users = $this->locator->mapper(User::class);

        $select = $users->newSelect()
            ->cols(['*'])
            ->orderBy(['username'])
            ->setPaging(2)
            ->page(2);

        self::assertSame(['edsger', 'grace'], array_map(fn (User $u): string => $u->username, $users->findBySelect($select)));
    }

    public function testJoinIntoEntities(): void
    {
        $users = $this->locator->mapper(User::class);
        $memberships = $this->locator->mapper(Membership::class);

        $ada = $users->findOneBy(['username' => 'ada']);
        self::assertNotNull($ada?->id);
        $membership = new Membership();
        $membership->userId = $ada->id;
        $membership->groupId = 7;
        $membership->role = 'owner';
        $memberships->addNew($membership);

        $select = $users->newSelect()
            ->cols(['users.*'])
            ->join('INNER', 'memberships AS m', 'm.user_id = users.id')
            ->where('m.group_id = :group', ['group' => 7])
            ->where('m.role = :role', ['role' => 'owner']);

        $owners = $users->findBySelect($select);
        self::assertCount(1, $owners);
        self::assertSame('ada', $owners[0]->username);
    }

    public function testAggregatesIntoRawRows(): void
    {
        $users = $this->locator->mapper(User::class);

        $select = $users->newSelect()
            ->cols(['is_active', 'COUNT(*) AS total'])
            ->groupBy(['is_active'])
            ->orderBy(['is_active']);

        $rows = $users->fetchAllRows($select);
        self::assertEquals([['is_active' => 0, 'total' => 1], ['is_active' => 1, 'total' => 3]], $rows);

        $latest = $users->fetchOneRow($users->newSelect()->cols(['MAX(id) AS max_id']));
        self::assertEquals(['max_id' => 4], $latest);
    }

    public function testTransactions(): void
    {
        $users = $this->locator->mapper(User::class);
        $pdo = $users->getConnection();

        $pdo->beginTransaction();
        $users->execute($users->newDelete()->where('username = :name', ['name' => 'ada']));
        $pdo->rollBack();

        self::assertSame(4, $users->count());
    }
}
