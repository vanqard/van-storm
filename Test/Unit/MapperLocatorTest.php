<?php

declare(strict_types=1);

namespace Vanqard\Test\Unit;

use Aura\Sql\ExtendedPdo;
use PHPUnit\Framework\TestCase;
use Vanqard\Storm\App\AbstractEntity;
use Vanqard\Storm\App\AbstractMapper;
use Vanqard\Storm\App\MapperLocator;
use Vanqard\Storm\Exceptions\InvalidMapperClassDeclaration;

final class MapperLocatorTest extends TestCase
{
    public function testMapperWithRequiredConstructorArgumentsIsRejected(): void
    {
        $this->expectException(InvalidMapperClassDeclaration::class);
        (new MapperLocator(new ExtendedPdo('sqlite::memory:')))->mapper(EntityWithBadMapper::class);
    }
}

/**
 * @extends AbstractMapper<EntityWithBadMapper>
 */
class BadMapper extends AbstractMapper
{
    public function __construct(public string $required)
    {
    }
}

class EntityWithBadMapper extends AbstractEntity
{
    public static string $table = 'bad';
    public static string $mapper = BadMapper::class;
    public int $id = 0;
}
