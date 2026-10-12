<?php

declare(strict_types=1);

namespace Vanqard\Test\Fixtures;

use Vanqard\Storm\App\AbstractEntity;
use Vanqard\Storm\Attributes\Column;
use Vanqard\Storm\Attributes\Entity;
use Vanqard\Storm\Types\DateTimeType;

#[Entity(table: 'users', mapper: UserMapper::class)]
class User extends AbstractEntity
{
    #[Column(type: 'int', name: 'id', primary: true, autoincrement: true)]
    public ?int $id = null;

    #[Column(type: 'string')]
    public string $username = '';

    #[Column(type: 'string', name: 'email_address', nullable: true)]
    public ?string $email = null;

    #[Column(type: 'bool')]
    public bool $isActive = true;

    #[Column(type: 'datetime')]
    public ?DateTimeType $createdAt = null;

    #[Column(type: 'object', nullable: true)]
    public ?Address $address = null;

    /** @var list<string>|null */
    #[Column(type: 'json', nullable: true)]
    public ?array $tags = null;

    /** Not mapped: must not be persisted */
    public string $transientNote = '';
}
