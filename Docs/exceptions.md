# Exceptions

Every exception Storm throws implements `Vanqard\Storm\Exceptions\StormExceptionInterface`.
To handle all of them in one place, catch that interface:

```php
try {
    $users->save($user);
} catch (StormExceptionInterface $e) {
    // a Storm configuration or usage error
} catch (\PDOException $e) {
    // a database error (constraint violation, connection failure, ...)
}
```

Database errors are not wrapped: they surface as the `PDOException` thrown by aura/sql.

| Exception | Thrown when |
|-----------|-------------|
| `InvalidEntityClassDeclarationException` | The class isn't an `AbstractEntity`, or declares no table. |
| `InvalidPrimaryKeyDeclarationException` | The entity has no primary key, a composite key is marked auto-increment, or `save()`/`remove()` is called on an entity whose key isn't set. |
| `InvalidMapperClassDeclaration` | The declared mapper isn't an `AbstractMapper`, needs constructor arguments, or has no entity class. |
| `InvalidParameterException` | Unsafe or unknown criteria/order-by identifiers, an invalid sort direction, an unknown column type, values that can't be converted, `DateTimeType` setters out of range, or a scalar `find()` on a composite key. |
| `DisallowedClassException` | A stored value names a class that isn't on the allow-list, or you try to store one. See [Types](types.md#the-class-allow-list). |
| `InvalidSerializerInstanceException` | The serializer passed to `SerializerProxy` doesn't have `serialize()`/`unserialize()` methods, or doesn't return a string. |
| `UnidentifiedEntityFieldNameException` | A lookup on the metadata names a field or column that doesn't exist. |
