<?php

declare(strict_types=1);

namespace Vanqard\Storm\Types;

use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Storm\Interfaces\SerializerInterface;
use Vanqard\Storm\Interfaces\TypeCastInterface;

/**
 * Maps the `type` declared on a #[Column] to the typecast that converts it.
 * Register your own types with register() to reuse them across projects.
 */
class TypeRegistry
{
    /**
     * @var array<string, TypeCastInterface>
     */
    private array $types = [];

    public function __construct(?SerializerInterface $serializer = null)
    {
        $int = new IntegerTypecast();
        $bool = new BooleanTypecast();
        $json = new JsonTypecast();

        $this->types = [
            'string' => new StringTypecast(),
            'int' => $int,
            'integer' => $int,
            'float' => new FloatTypecast(),
            'bool' => $bool,
            'boolean' => $bool,
            'datetime' => new DateTimeTypecast(),
            'object' => new ObjectTypecast($serializer),
            'json' => $json,
            'array' => $json,
        ];
    }

    public function register(string $type, TypeCastInterface $typecast): static
    {
        $this->types[strtolower($type)] = $typecast;
        return $this;
    }

    public function has(string $type): bool
    {
        return isset($this->types[strtolower($type)]);
    }

    /**
     * @throws InvalidParameterException
     */
    public function get(string $type): TypeCastInterface
    {
        $key = strtolower($type);
        if (!isset($this->types[$key])) {
            throw new InvalidParameterException(
                sprintf("No typecast has been registered for the column type '%s'", $type)
            );
        }

        return $this->types[$key];
    }
}
