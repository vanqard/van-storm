<?php

declare(strict_types=1);

namespace Vanqard\Test\Fixtures;

use Vanqard\Storm\App\AbstractEntity;
use Vanqard\Storm\Types\DateTimeType;

/**
 * Entity with no attributes, mapped entirely by convention.
 */
class ConventionPost extends AbstractEntity
{
    public static string $table = 'convention_posts';

    public int $id = 0;

    public string $postTitle = '';

    public bool $isPublished = false;

    public ?DateTimeType $publishedAt = null;

    public mixed $meta = null;
}
