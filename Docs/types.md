# Types

The `type` on a `#[Column]` picks a **typecast** from the `TypeRegistry`. A typecast
implements `Vanqard\Storm\Interfaces\TypeCastInterface`:

```php
public function getDatabaseValue(mixed $value = null): mixed;  // PHP → database
public function getPhpValue(mixed $value): mixed;              // database → PHP
```

Every built-in typecast passes `null` through unchanged in both directions.

## Built-in types

| Type(s) | PHP value | Database value | Typecast |
|---------|-----------|----------------|----------|
| `string` | `string` | string | `StringTypecast` |
| `int`, `integer` | `int` | int | `IntegerTypecast` |
| `float` | `float` | float | `FloatTypecast` |
| `bool`, `boolean` | `bool` | `0` / `1` | `BooleanTypecast` |
| `datetime` | `DateTimeType` | `Y-m-d H:i:s` | `DateTimeTypecast` |
| `object` | any object or array of objects | JSON with class info (`@type`) | `ObjectTypecast` |
| `json`, `array` | array / scalar | plain JSON | `JsonTypecast` |

Type names are case-insensitive.

### `object`

`object` columns are serialized with [zumba/json-serializer](https://github.com/zumba/json-serializer)
and **rehydrate into their original classes**, including nested objects. Store them in a
`TEXT` (or `JSON`) column.

#### The class allow-list

The stored JSON names the class to recreate in a `@type` key. If someone can write to that
column, they could otherwise make your application create any class it can autoload. To
prevent that, Storm only rehydrates classes on an **allow-list**, and the default list is
empty. Until you configure one, storing or reading an object throws
`DisallowedClassException`. Plain arrays and scalars are unaffected.

Generate the list with the bundled script. It scans your source tree for concrete classes
and enums without loading any of the code:

```bash
vendor/bin/storm-allowlist --output=config/storm-allowed-classes.php src/Model
```

Then pass the file, or a list of class names, to the locator:

```php
$storm = new MapperLocator($pdo, allowedClasses: __DIR__ . '/config/storm-allowed-classes.php');
// or
$storm = new MapperLocator($pdo, allowedClasses: [Address::class, Preferences::class]);
```

Things to know:

- Names must match exactly. Every class that can appear in a stored value needs to be
  listed, **including nested ones**. For built-in or vendor classes the scan can't see, add
  `--include`, for example `--include=DateTimeImmutable` or
  `--include='Vanqard\Storm\Types\DateTimeType'`.
- Skip directories with `--exclude=<dir>`, and preview the result with `--dry-run`.
- Commit the generated file, and run the script again whenever you add a class that gets
  stored in an `object` column.
- The script exits with code `2` if some files couldn't be parsed. The list is still
  written, and the skipped files are printed.

#### Other serializers

To use a different serializer, wrap it in a `SerializerProxy`. It can be any object with
`serialize()` and `unserialize()` methods. The allow-list is applied through its
`setAllowedClasses()` method if it has one. Otherwise, enforcing restrictions is up to the
serializer.

```php
$storm = new MapperLocator($pdo, new SerializerProxy($mySerializer, [Address::class]));
$storm = new MapperLocator($pdo, SerializerProxy::fromAllowListFile('config/storm-allowed-classes.php'));
```

`SerializerProxy::unrestricted()` removes the restriction completely. Only use it if no one
but your application can ever write to the database.

### `datetime` and `DateTimeType`

`DateTimeType` extends `\DateTime`. It serializes to ISO 8601 in JSON and when cast to a
string, and adds setters with bounds checks: `setYear()`, `setMonth()`, `setDay()`,
`setHour()`, `setMinutes()` and `setSeconds()`. An out-of-range value throws
`InvalidParameterException`. Writing accepts any `DateTimeInterface` or a string. Reading
throws `InvalidParameterException` if the stored value can't be parsed.

## Custom types

Register your own typecast once and use it in any entity:

```php
use Vanqard\Storm\Interfaces\TypeCastInterface;

final class MoneyTypecast implements TypeCastInterface
{
    public function getDatabaseValue(mixed $value = null): ?int
    {
        return $value?->pence;                      // store as integer pence
    }

    public function getPhpValue(mixed $value): ?Money
    {
        return $value === null ? null : new Money((int) $value);
    }
}

$storm = new MapperLocator($pdo);
$storm->getTypeRegistry()->register('money', new MoneyTypecast());

#[Column(type: 'money')]
public ?Money $price = null;
```

Ship your typecasts in a shared package, and every project that registers them can use the
same column types.

## Type inference without a declared type

If a `#[Column]` has no `type` (or the entity is convention-mapped), Storm uses the
property's declared type: `int`, `float`, `bool`, or `DateTime`/`DateTimeInterface`/`DateTimeType`.
For any other property it falls back to the naming conventions described in
[Entities](entities.md#convention-mapped-entities).
