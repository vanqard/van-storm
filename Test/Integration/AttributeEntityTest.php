<?php

declare(strict_types=1);

namespace Vanqard\Test\Integration;

use Vanqard\Storm\App\MapperLocator;
use Vanqard\Storm\App\SerializerProxy;
use Vanqard\Storm\Exceptions\DisallowedClassException;
use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Storm\Exceptions\InvalidPrimaryKeyDeclarationException;
use Vanqard\Storm\Tools\AllowListWriter;
use Vanqard\Storm\Types\DateTimeType;
use Vanqard\Test\Fixtures\Address;
use Vanqard\Test\Fixtures\User;
use Vanqard\Test\Fixtures\UserMapper;

final class AttributeEntityTest extends DatabaseTestCase
{
    private function newUser(string $username = 'ada'): User
    {
        $user = new User();
        $user->username = $username;
        $user->email = "{$username}@example.com";
        $user->createdAt = new DateTimeType('2026-01-02 03:04:05');
        $user->address = new Address('1 Analytical St', 'London', new Address('Old Rd', 'Bath'));
        $user->tags = ['admin', 'math'];
        $user->transientNote = 'ignored';
        return $user;
    }

    public function testLocatorReturnsDeclaredMapper(): void
    {
        $mapper = $this->locator->mapper(User::class);
        self::assertInstanceOf(UserMapper::class, $mapper);
        self::assertSame($mapper, $this->locator->mapper(User::class));
    }

    public function testFullRoundTrip(): void
    {
        $mapper = $this->locator->mapper(User::class);

        $user = $mapper->addNew($this->newUser());
        self::assertIsInt($user->id);
        self::assertGreaterThan(0, $user->id);

        $found = $mapper->find($user->id);
        self::assertInstanceOf(User::class, $found);
        self::assertNotSame($user, $found);
        self::assertSame('ada', $found->username);
        self::assertSame('ada@example.com', $found->email);
        self::assertTrue($found->isActive);
        self::assertInstanceOf(DateTimeType::class, $found->createdAt);
        self::assertSame('2026-01-02 03:04:05', $found->createdAt->format('Y-m-d H:i:s'));
        self::assertSame(['admin', 'math'], $found->tags);
        self::assertSame('', $found->transientNote);

        // The headline promise: object columns rehydrate into their original classes
        self::assertInstanceOf(Address::class, $found->address);
        self::assertEquals($user->address, $found->address);
        self::assertInstanceOf(Address::class, $found->address->previous);

        $found->isActive = false;
        $found->address = null;
        $mapper->save($found);

        $reloaded = $mapper->find($user->id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive);
        self::assertNull($reloaded->address);

        self::assertSame(1, $mapper->count());
        self::assertTrue($mapper->remove($reloaded));
        self::assertFalse($mapper->remove($reloaded));
        self::assertSame(0, $mapper->count());
        self::assertNull($mapper->find($user->id));
    }

    public function testUnmappedColumnsInTheDatabaseAreIgnored(): void
    {
        $this->pdo->exec("INSERT INTO users (username, is_active) VALUES ('raw', 1)");
        $found = $this->locator->mapper(User::class)->findOneBy(['username' => 'raw']);
        self::assertNotNull($found);
        self::assertNull($found->address);
    }

    public function testFindersReturnEmptyResultsInsteadOfThrowing(): void
    {
        $mapper = $this->locator->mapper(User::class);
        self::assertNull($mapper->find(999));
        self::assertSame([], $mapper->findAll());
        self::assertSame([], $mapper->findBy(['username' => 'nobody']));
        self::assertNull($mapper->findOneBy(['username' => 'nobody']));
    }

    public function testFindByAcceptsFieldOrColumnNamesNullsAndLists(): void
    {
        $mapper = $this->locator->mapper(User::class);
        foreach (['carol', 'alice', 'bob'] as $name) {
            $mapper->addNew($this->newUser($name));
        }
        $noEmail = $this->newUser('dave');
        $noEmail->email = null;
        $noEmail->isActive = false;
        $mapper->addNew($noEmail);

        $names = fn (array $users): array => array_map(fn (User $u): string => $u->username, $users);

        self::assertSame(['alice', 'bob', 'carol'], $names($mapper->findBy(['isActive' => true], ['username' => 'asc'])));
        self::assertSame(['alice', 'bob', 'carol'], $names($mapper->findBy(['is_active' => 1], ['username'])));
        self::assertSame(['dave'], $names($mapper->findBy(['email' => null])));
        self::assertSame(['carol', 'bob'], $names($mapper->findBy(['username' => ['bob', 'carol']], ['username' => 'DESC'])));
        self::assertSame(['bob'], $names($mapper->findBy([], ['username' => 'ASC'], 1, 1)));
        self::assertSame([], $mapper->findBy(['username' => []]));
        self::assertSame(3, $mapper->count(['isActive' => true]));
        self::assertInstanceOf(UserMapper::class, $mapper);
        self::assertSame('alice', $mapper->findByUsername('alice')?->username);
    }

    public function testRawRowAccess(): void
    {
        $mapper = $this->locator->mapper(User::class);
        $mapper->addNew($this->newUser());

        $select = $mapper->newSelect()->cols(['username', 'email_address']);
        self::assertSame(['username' => 'ada', 'email_address' => 'ada@example.com'], $mapper->fetchOneRow($select));
        self::assertCount(1, $mapper->fetchAllRows($select));
        self::assertNull($mapper->fetchOneRow($mapper->newSelect()->cols(['*'])->where('id = -1')));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1?: array<int|string, string>}>
     */
    public static function injectionAttempts(): iterable
    {
        yield 'criteria key' => [['username = username OR 1=1 --' => 'x']];
        yield 'order by key' => [[], ['username; DROP TABLE users' => 'ASC']];
        yield 'order by direction' => [[], ['username' => 'ASC, (SELECT 1)']];
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<int|string, string>|null $orderBy
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('injectionAttempts')]
    public function testUnsafeIdentifiersAreRejected(array $criteria, ?array $orderBy = null): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->locator->mapper(User::class)->findBy($criteria, $orderBy);
    }

    public function testTamperedObjectColumnIsRejected(): void
    {
        // Simulates an attacker writing a payload that names an unexpected class
        $payload = SerializerProxy::unrestricted()->serialize(new \ArrayObject([1]));
        $this->pdo->perform(
            'INSERT INTO users (username, is_active, address) VALUES (:u, 1, :a)',
            ['u' => 'mallory', 'a' => $payload]
        );

        $this->expectException(DisallowedClassException::class);
        $this->expectExceptionMessage('ArrayObject');
        $this->locator->mapper(User::class)->findOneBy(['username' => 'mallory']);
    }

    public function testObjectColumnsAreDeniedWithoutAnAllowList(): void
    {
        $locator = new MapperLocator($this->pdo);

        $this->expectException(DisallowedClassException::class);
        $locator->mapper(User::class)->addNew($this->newUser());
    }

    public function testAllowListCanBeLoadedFromAGeneratedFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'storm-allow');
        self::assertIsString($file);
        (new AllowListWriter())->write($file, [Address::class]);

        try {
            $mapper = (new MapperLocator($this->pdo, allowedClasses: $file))->mapper(User::class);
            $user = $mapper->addNew($this->newUser());
            self::assertInstanceOf(Address::class, $mapper->find($user->id)?->address);
        } finally {
            unlink($file);
        }
    }

    public function testSerializerAndAllowedClassesAreMutuallyExclusive(): void
    {
        $this->expectException(InvalidParameterException::class);
        new MapperLocator($this->pdo, new SerializerProxy(), allowedClasses: [Address::class]);
    }

    public function testSaveRequiresAPrimaryKey(): void
    {
        $this->expectException(InvalidPrimaryKeyDeclarationException::class);
        $this->locator->mapper(User::class)->save($this->newUser());
    }
}
