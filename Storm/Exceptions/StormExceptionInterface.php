<?php

declare(strict_types=1);

namespace Vanqard\Storm\Exceptions;

/**
 * Marker interface implemented by every exception Storm throws, so consumers
 * can catch them all with a single type.
 */
interface StormExceptionInterface extends \Throwable
{
}
