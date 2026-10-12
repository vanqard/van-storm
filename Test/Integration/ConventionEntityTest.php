<?php

declare(strict_types=1);

namespace Vanqard\Test\Integration;

use Vanqard\Storm\App\GenericMapper;
use Vanqard\Storm\App\SerializerProxy;
use Vanqard\Storm\Exceptions\DisallowedClassException;
use Vanqard\Storm\Types\DateTimeType;
use Vanqard\Test\Fixtures\Address;
use Vanqard\Test\Fixtures\ConventionPost;

final class ConventionEntityTest extends DatabaseTestCase
{
    public function testFullRoundTripByConvention(): void
    {
        $mapper = $this->locator->mapper(ConventionPost::class);
        self::assertInstanceOf(GenericMapper::class, $mapper);

        $post = new ConventionPost();
        $post->postTitle = 'Hello';
        $post->isPublished = true;
        $post->publishedAt = new DateTimeType('2026-05-06 07:08:09');
        $post->meta = new Address('Somewhere', 'Leeds');

        $mapper->addNew($post);
        self::assertGreaterThan(0, $post->id);

        $found = $mapper->find($post->id);
        self::assertInstanceOf(ConventionPost::class, $found);
        self::assertSame('Hello', $found->postTitle);
        self::assertTrue($found->isPublished);
        self::assertSame('2026-05-06 07:08:09', $found->publishedAt?->format('Y-m-d H:i:s'));
        self::assertEquals(new Address('Somewhere', 'Leeds'), $found->meta);

        $found->postTitle = 'Updated';
        $mapper->save($found);
        self::assertSame('Updated', $mapper->findOneBy(['post_title' => 'Updated'])?->postTitle);

        self::assertSame(1, $mapper->count(['isPublished' => true]));
        self::assertTrue($mapper->remove($found));
        self::assertSame(0, $mapper->count());
    }

    public function testTamperedConventionColumnIsRejected(): void
    {
        $payload = SerializerProxy::unrestricted()->serialize(new \ArrayObject([1]));
        $this->pdo->perform(
            'INSERT INTO convention_posts (post_title, is_published, meta) VALUES (:t, 0, :m)',
            ['t' => 'evil', 'm' => $payload]
        );

        $this->expectException(DisallowedClassException::class);
        $this->locator->mapper(ConventionPost::class)->findOneBy(['postTitle' => 'evil']);
    }
}
