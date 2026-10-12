<?php

declare(strict_types=1);

namespace Vanqard\Storm\Types;

use DateTime;
use JsonSerializable;
use Stringable;
use Vanqard\Storm\Exceptions\InvalidParameterException;

/**
 * DateTime subclass used for datetime columns. Serializes to ISO 8601 and
 * offers bounds-checked setters for individual date/time components.
 */
class DateTimeType extends DateTime implements JsonSerializable, Stringable
{
    public const string DATABASE_FORMAT = 'Y-m-d H:i:s';

    public function jsonSerialize(): string
    {
        return $this->format('c');
    }

    /**
     * @throws InvalidParameterException
     */
    public function setDay(int $dayNum): static
    {
        $this->assertRange('day', $dayNum, 1, 31);
        $this->setDate((int) $this->format('Y'), (int) $this->format('m'), $dayNum);
        return $this;
    }

    /**
     * @throws InvalidParameterException
     */
    public function setMonth(int $monthNum): static
    {
        $this->assertRange('month', $monthNum, 1, 12);
        $this->setDate((int) $this->format('Y'), $monthNum, (int) $this->format('j'));
        return $this;
    }

    /**
     * @throws InvalidParameterException
     */
    public function setYear(int $yearNum): static
    {
        $this->assertRange('year', $yearNum, 1, 9999);
        $this->setDate($yearNum, (int) $this->format('m'), (int) $this->format('j'));
        return $this;
    }

    /**
     * @throws InvalidParameterException
     */
    public function setHour(int $hourNum): static
    {
        $this->assertRange('hour', $hourNum, 0, 23);
        $this->setTime($hourNum, (int) $this->format('i'), (int) $this->format('s'));
        return $this;
    }

    /**
     * @throws InvalidParameterException
     */
    public function setMinutes(int $minuteNum): static
    {
        $this->assertRange('minute', $minuteNum, 0, 59);
        $this->setTime((int) $this->format('H'), $minuteNum, (int) $this->format('s'));
        return $this;
    }

    /**
     * @throws InvalidParameterException
     */
    public function setSeconds(int $secondNum): static
    {
        $this->assertRange('second', $secondNum, 0, 59);
        $this->setTime((int) $this->format('H'), (int) $this->format('i'), $secondNum);
        return $this;
    }

    public function __toString(): string
    {
        return $this->format(DATE_ATOM);
    }

    /**
     * @throws InvalidParameterException
     */
    private function assertRange(string $unit, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidParameterException(
                sprintf("The %s must be between %d and %d, %d given", $unit, $min, $max, $value)
            );
        }
    }
}
