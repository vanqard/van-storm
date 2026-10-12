# Mappers

A mapper loads and stores one entity class. Get mappers from the `MapperLocator`:

```php
$storm = new MapperLocator($pdo, allowedClasses: 'config/storm-allowed-classes.php');
// also optional: serializer, metadata factory, type registry
$users = $storm->mapper(User::class);          // AbstractMapper<User>, cached per class
```

## Reading

| Method | Returns |
|--------|---------|
| `find(mixed $id)` | `?Entity`. For composite keys, pass an array: `find(['userId' => 1, 'groupId' => 2])`. |
| `findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null)` | `list<Entity>` (empty if nothing matches) |
| `findOneBy(array $criteria, ?array $orderBy = null)` | `?Entity` |
| `findAll()` | `list<Entity>` |
| `findBySelect(SelectInterface $select)` | `list<Entity>` hydrated from your own query |
| `count(array $criteria = [])` | `int` |

Criteria rules:

- **Keys** can be property names (`isActive`) or column names (`is_active`). An unmapped key
  is accepted only if it's a plain SQL identifier, which stops injection through criteria
  keys. Storm always quotes identifiers.
- **Values** go through the column's typecast and are always bound as parameters.
  - `null` produces `IS NULL`.
  - An array produces `IN (...)`. An empty array matches nothing.
- **`$orderBy`** takes either `['field' => 'ASC' | 'DESC']` or `['field', ...]` (which means
  ascending). Any other direction throws `InvalidParameterException`.

## Writing

| Method | Description |
|--------|-------------|
| `addNew(Entity $e): Entity` | Inserts the entity. If the auto-increment key is empty, the database generates it and Storm writes it back to the entity. |
| `save(Entity $e): Entity` | Updates every mapped non-key column, matching on the primary key. Throws `InvalidPrimaryKeyDeclarationException` if the key isn't set. |
| `remove(Entity $e): bool` | Deletes by primary key. Returns `true` if a row was deleted. |
| `execute(InsertInterface\|UpdateInterface\|DeleteInterface $q): int` | Runs a hand-built write query and returns the number of affected rows. See [Query builder](query-builder.md#inserts-updates-and-deletes). |

## Custom mappers

Subclass `AbstractMapper` and point the entity at it:

```php
/** @extends AbstractMapper<User> */
class UserMapper extends AbstractMapper
{
    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    /** @return list<User> */
    public function recentlyActive(int $days): array
    {
        $select = $this->newSelect()
            ->cols(['*'])
            ->where('last_seen_at > :since', ['since' => (new DateTimeType("-{$days} days"))->format('Y-m-d H:i:s')]);

        return $this->findBySelect($select);
    }
}
```

The `MapperLocator` creates mappers itself and injects their dependencies through setters,
so **a mapper must be constructable without arguments**. The locator raises
`InvalidMapperClassDeclaration` otherwise.

## Query builders and raw rows

For queries the finder methods can't express, `newSelect()`, `newInsert()`, `newUpdate()`
and `newDelete()` return aura/sqlquery builders scoped to the mapper's table. Run them with
`findBySelect()`, `fetchAllRows()`, `fetchOneRow()` or `execute()`. The
[Query builder](query-builder.md) page covers this in full, including joins, paging,
aggregates, transactions, and the safety rules that differ from the finder methods.

## Hydration helpers

- `hydrate(array $row): Entity` turns a database row into an entity.
- `extract(Entity $e): array` turns an entity into a row of database values keyed by column
  name.

`toDbProperties()` and `toEntityProperties()` apply the convention-based conversion to plain
arrays.

## Dialects

`getQueryFactory()` picks the aura/sqlquery dialect from `PDO::ATTR_DRIVER_NAME` (`mysql`,
`pgsql`, `sqlite`, or `sqlsrv`/`dblib`; any other driver uses the common dialect). To
override it, call `setQueryFactory()`.
