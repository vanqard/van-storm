<?php

declare(strict_types=1);

namespace Vanqard\Test\Unit;

use PHPUnit\Framework\TestCase;
use Vanqard\Storm\Exceptions\InvalidEntityClassDeclarationException;
use Vanqard\Storm\Exceptions\InvalidPrimaryKeyDeclarationException;
use Vanqard\Storm\Exceptions\StormExceptionInterface;
use Vanqard\Storm\Metadata\EntityMetadataFactory;
use Vanqard\Test\Fixtures\Address;
use Vanqard\Test\Fixtures\ConventionPost;
use Vanqard\Test\Fixtures\InvalidCompositeKey;
use Vanqard\Test\Fixtures\Membership;
use Vanqard\Test\Fixtures\User;
use Vanqard\Test\Fixtures\UserMapper;

final class EntityMetadataFactoryTest extends TestCase
{
    public function testAttributeEntity(): void
    {
        $factory = new EntityMetadataFactory();
        $meta = $factory->getMetadata(User::class);

        self::assertSame($meta, $factory->getMetadata(User::class));
        self::assertSame('users', $meta->getTable());
        self::assertSame(UserMapper::class, $meta->getMapper());
        self::assertSame(User::class, $meta->getEntityClass());
        self::assertTrue($meta->hasAutoIncrementingPrimaryKey());
        self::assertSame('id', $meta->getAutoIncrementingPrimaryKeyFieldName());
        self::assertSame('email_address', $meta->getColumnNameForFieldName('email'));
        self::assertSame('created_at', $meta->getColumnNameForFieldName('createdAt'));
        self::assertSame('object', $meta->getColumnSpecForFieldName('address')->getType());
        self::assertArrayNotHasKey('transientNote', $meta->getColumns());
    }

    public function testConventionEntity(): void
    {
        $meta = (new EntityMetadataFactory())->getMetadata(ConventionPost::class);

        self::assertSame('convention_posts', $meta->getTable());
        self::assertSame('', $meta->getMapper());
        self::assertSame(['id'], $meta->getPrimaryKeyFieldsAsList());
        self::assertSame('post_title', $meta->getColumnNameForFieldName('postTitle'));
        self::assertSame('', $meta->getColumnSpecForFieldName('postTitle')->getType());
    }

    public function testCompositeKey(): void
    {
        $meta = (new EntityMetadataFactory())->getMetadata(Membership::class);
        self::assertTrue($meta->hasCompositePrimaryKey());
        self::assertFalse($meta->hasAutoIncrementingPrimaryKey());
        self::assertSame(['user_id', 'group_id'], $meta->getPrimaryKeyColumnsAsList());
    }

    public function testAutoIncrementingCompositeKeyIsRejected(): void
    {
        $this->expectException(InvalidPrimaryKeyDeclarationException::class);
        (new EntityMetadataFactory())->getMetadata(InvalidCompositeKey::class);
    }

    public function testNonEntityClassIsRejected(): void
    {
        try {
            (new EntityMetadataFactory())->getMetadata(Address::class);
            self::fail('Expected an exception');
        } catch (InvalidEntityClassDeclarationException $e) {
            self::assertInstanceOf(StormExceptionInterface::class, $e);
        }
    }

    public function testNameConversion(): void
    {
        self::assertSame('created_at', EntityMetadataFactory::toColumnName('createdAt'));
        self::assertSame('id', EntityMetadataFactory::toColumnName('id'));
        self::assertSame('createdAt', EntityMetadataFactory::toFieldName('created_at'));
        self::assertSame('isActive', EntityMetadataFactory::toFieldName('is_active'));
    }
}
