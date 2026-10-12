<?php

declare(strict_types=1);

namespace Vanqard\Storm\App;

/**
 * Mapper used by the MapperLocator for entities that do not declare their own
 * mapper class.
 *
 * @template T of AbstractEntity
 * @extends AbstractMapper<T>
 */
final class GenericMapper extends AbstractMapper
{
}
