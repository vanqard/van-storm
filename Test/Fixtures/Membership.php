<?php

declare(strict_types=1);

namespace Vanqard\Test\Fixtures;

use Vanqard\Storm\App\AbstractEntity;
use Vanqard\Storm\Attributes\Column;
use Vanqard\Storm\Attributes\Entity;

#[Entity(table: 'memberships')]
class Membership extends AbstractEntity
{
    #[Column(type: 'int', primary: true)]
    public int $userId;

    #[Column(type: 'int', primary: true)]
    public int $groupId;

    #[Column(type: 'string')]
    public string $role = 'member';
}
