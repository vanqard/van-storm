# Entities

Every entity extends `Vanqard\Storm\App\AbstractEntity`. There are two ways to describe how
an entity maps to its table:

1. **Attributes (recommended).** Add `#[Entity]` to the class and `#[Column]` to each
   persisted property.
2. **Conventions.** Use no `#[Column]` attributes. Storm then maps every
   non-static property.

Storm reads this metadata once per class with reflection and caches it
(`Vanqard\Storm\Metadata\EntityMetadataFactory`).

## Attribute-mapped entities

```php
#[Entity(table: 'users', mapper: UserMapper::class)]
class User extends AbstractEntity
{
    #[Column(type: 'int', name: 'id', primary: true, autoincrement: true)]
    public ?int $id = null;

    #[Column(type: 'string', name: 'email_address', nullable: true)]
    public ?string $email = null;

    #[Column(type: 'json')]
    public array $tags = [];

    public string $notPersisted = '';   // no #[Column] → ignored by the mapper
}
```

### `#[Entity]`

| Parameter | Default | Meaning |
|-----------|---------|---------|
| `table`   | `''`    | Table name. Falls back to the static `$table` property. Storm requires one or the other. |
| `mapper`  | `''`    | Mapper class (a subclass of `AbstractMapper`). Falls back to the static `$mapper` property, then to `GenericMapper`. |

### `#[Column]`

| Parameter       | Default | Meaning |
|-----------------|---------|---------|
| `type`          | `''`    | Name of a type in the `TypeRegistry` (see [Types](types.md)). If it's empty, Storm infers the type from the property's declared type, then falls back to the naming conventions. |
| `name`          | `''`    | Column name. Defaults to the snake_case form of the property name (`createdAt` → `created_at`). |
| `nullable`      | `false` | Informational only. |
| `primary`       | `false` | Marks the column as part of the primary key. Mark several columns to make a composite key. |
| `autoincrement` | `false` | The database generates the value. `addNew()` leaves out an empty value (null or 0) and fills it in after the insert. Composite keys can't be auto-incrementing. |

Columns can live on public, protected or private properties. Storm reads and writes them
through reflection.

## Convention-mapped entities

If no property has a `#[Column]` attribute, Storm maps the entity by convention:

- The table and mapper come from `#[Entity]`, or from the static `$table` and `$mapper`
  properties.
- Every non-static property is a column. Its name is the property name in snake_case.
- The primary key is the property named by the static `$primaryKey` (default `'id'`), and
  Storm treats it as auto-incrementing.
- If a property has a scalar or date type (`int`, `float`, `bool`, `DateTime`,
  `DateTimeInterface`, `DateTimeType`), that type picks the typecast. Otherwise Storm
  converts values by naming convention:
  - **Writing:** `DateTimeInterface` values become `Y-m-d H:i:s`, booleans become `0`/`1`,
    and other objects or arrays go through the serializer.
  - **Reading:** serialized JSON that contains `"@type"` is unserialized. Strings in a
    property ending in `At` become `DateTimeType`. Columns prefixed `is_`, `has_` or `can_`
    become booleans.

```php
class ConventionPost extends AbstractEntity
{
    public static string $table = 'convention_posts';

    public int $id = 0;
    public string $postTitle = '';
    public ?DateTimeType $publishedAt = null;
}
```

## Hydration rules

- The mapper creates entities **without calling their constructor**, the same way Doctrine
  does. Default property values still apply.
- Columns in the result that aren't mapped to a property are ignored.
- Writes skip typed properties that aren't initialized.

## `AbstractEntity` helpers

| Method | Description |
|--------|-------------|
| `fromArray(array $data): static` | Sets accessible properties from an array keyed by property name. |
| `toArray(): array` | `get_object_vars()` of the entity. |
| `jsonSerialize(): array` | Same as `toArray()`. |
| `getPrimaryKeyName()`, `getTableName()`, `getMapper()` | Read the static configuration properties. |

`toArray()` is a convenience method. It is **not** what the mapper persists: the mapper
writes only the mapped columns.
