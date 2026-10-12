<?php

declare(strict_types=1);

namespace Vanqard\Storm\Types;

use Vanqard\Storm\App\SerializerProxy;
use Vanqard\Storm\Interfaces\SerializerInterface;
use Vanqard\Storm\Interfaces\TypeCastInterface;

/**
 * Stores objects (and arrays of objects) as serialized JSON text and
 * rehydrates them into their original classes. Uses zumba/json-serializer
 * unless another serializer is supplied.
 *
 * Only classes on the serializer's allow-list can be written or read; the
 * default SerializerProxy allows none (see `vendor/bin/storm-allowlist`).
 */
class ObjectTypecast implements TypeCastInterface
{
    private SerializerInterface $jsonSerializer;

    public function __construct(?SerializerInterface $jsonSerializer = null)
    {
        $this->jsonSerializer = $jsonSerializer ?? new SerializerProxy();
    }

    public function setJsonSerializer(SerializerInterface $jsonSerializer): ObjectTypecast
    {
        $this->jsonSerializer = $jsonSerializer;
        return $this;
    }

    public function getDatabaseValue(mixed $value = null): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->jsonSerializer->serialize($value);
    }

    public function getPhpValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->jsonSerializer->unserialize($value);
    }
}
