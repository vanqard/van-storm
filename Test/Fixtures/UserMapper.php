<?php

declare(strict_types=1);

namespace Vanqard\Test\Fixtures;

use Vanqard\Storm\App\AbstractMapper;

/**
 * @extends AbstractMapper<User>
 */
class UserMapper extends AbstractMapper
{
    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }
}
