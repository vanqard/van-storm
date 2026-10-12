<?php

declare(strict_types=1);

namespace Vanqard\Test\Fixtures;

use Vanqard\Storm\App\AbstractEntity;
use Vanqard\Storm\Attributes\Column;
use Vanqard\Storm\Attributes\Entity;

#[Entity(table: 'invalid')]
class InvalidCompositeKey extends AbstractEntity
{
    #[Column(type: 'int', primary: true, autoincrement: true)]
    public int $a;

    #[Column(type: 'int', primary: true)]
    public int $b;
}
