# Query builder

When the finder methods (`findBy()`, `count()` and the rest) aren't enough, build the query
yourself with [aura/sqlquery](https://github.com/auraphp/Aura.SqlQuery). Every mapper hands
out builders that are already pointed at its table and use the connection's SQL dialect:

| Method | Returns | Run it with |
|--------|---------|-------------|
| `newSelect()` | `SelectInterface` | `findBySelect()` for entities, `fetchAllRows()` / `fetchOneRow()` for raw rows |
| `newInsert()` | `InsertInterface` | `execute()` |
| `newUpdate()` | `UpdateInterface` | `execute()` |
| `newDelete()` | `DeleteInterface` | `execute()` |

Use them from outside a mapper (`$users->newSelect()`) or inside a
[custom mapper](mappers.md#custom-mappers) (`$this->newSelect()`).

## Selecting entities

```php
$users = $storm->mapper(User::class);

$select = $users->newSelect()
    ->cols(['*'])
    ->where('is_active = :active', ['active' => 1])
    ->where('username IN (:names)', ['names' => ['ada', 'grace', 'alan']])
    ->orderBy(['username DESC'])
    ->limit(10);

$found = $users->findBySelect($select);   // list<User>
```

- Repeated `where()` calls are joined with `AND`. Use `orWhere()` for `OR`.
- Bind an array to `IN (:name)` and it expands into one placeholder per value.
- `findBySelect()` hydrates each row into an entity in the same way the finders do. Columns
  that don't map to a property are ignored.

### Paging

```php
$select = $users->newSelect()
    ->cols(['*'])
    ->orderBy(['username'])
    ->setPaging(2)     // rows per page
    ->page(2);         // 1-based page number
```

### Joins

The builder's `FROM` is the mapper's table, unaliased. Refer to it by its table name, and
select only its columns (`users.*`) so the rows still hydrate into entities:

```php
$select = $users->newSelect()
    ->cols(['users.*'])
    ->join('INNER', 'memberships AS m', 'm.user_id = users.id')
    ->where('m.group_id = :group', ['group' => 7])
    ->where('m.role = :role', ['role' => 'owner']);

$owners = $users->findBySelect($select);
```

## Reports and aggregates (raw rows)

For results that aren't entities, use `fetchAllRows()` (returns a list of rows) or
`fetchOneRow()` (returns the first row, or `null` if there isn't one):

```php
$select = $users->newSelect()
    ->cols(['is_active', 'COUNT(*) AS total'])
    ->groupBy(['is_active'])
    ->orderBy(['is_active']);

$rows = $users->fetchAllRows($select);
// [['is_active' => 0, 'total' => 1], ['is_active' => 1, 'total' => 3]]

$latest = $users->fetchOneRow($users->newSelect()->cols(['MAX(id) AS max_id']));
```

The types of the returned values depend on the PDO driver. MySQL usually returns numbers as
strings, so cast them if you need to.

## Inserts, updates and deletes

`execute()` runs a write builder and returns the number of rows it affected:

```php
$insert = $users->newInsert()->cols([
    'username' => 'barbara',
    'is_active' => 1,
    'created_at' => '2026-01-01 09:00:00',
]);
$users->execute($insert);                       // 1

$update = $users->newUpdate()
    ->cols(['is_active' => 0])
    ->where('username IN (:names)', ['names' => ['ada', 'grace']]);
$users->execute($update);                       // 2

$delete = $users->newDelete()->where('is_active = :active', ['active' => 0]);
$users->execute($delete);                       // number of rows deleted
```

These are bulk operations on rows. To insert, update or delete a single entity, use
`addNew()`, `save()` and `remove()` instead. Those methods apply the entity's mapping and
types, and they fill in generated ids. Hand-built queries do neither.

## How this differs from the finder methods

The finder and entity methods protect you in ways a hand-built query can't:

1. **No typecasting.** Values in `cols()` and `where()` go to the database exactly as you
   give them. Format dates yourself (`$date->format('Y-m-d H:i:s')`), pass booleans as
   `1`/`0`, and serialize objects yourself. To get the database form of a whole entity, use
   `$users->extract($entity)`.
2. **No identifier checks.** Storm doesn't validate or quote column names in `where()`,
   `orderBy()` or `join()` conditions. Never build those strings from user input. Quote
   reserved words with `$users->getConnection()->quoteName('order')`.
3. **Always bind values.** Use `:name` placeholders with the bind array, as in the examples
   above. Never put values straight into the SQL string.

## Transactions

The connection is an aura/sql `ExtendedPdo`, so the standard PDO transaction methods are
available:

```php
$pdo = $users->getConnection();

$pdo->beginTransaction();
try {
    $users->execute($users->newDelete()->where('username = :name', ['name' => 'ada']));
    $memberships->execute($memberships->newDelete()->where('user_id = :id', ['id' => $ada->id]));
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

## Raw SQL

When the builder gets in the way, use the connection directly. It works with plain SQL and
bound values:

```php
$rows = $users->getConnection()->fetchAll(
    'SELECT * FROM users WHERE created_at > :since',
    ['since' => '2026-01-01 00:00:00']
);

$entities = array_map($users->hydrate(...), $rows);   // turn rows back into entities
```

## Further reading

The full builder API, including sub-selects, `UNION`, `HAVING`, multi-row inserts and the
dialect-specific extras, is in the
[aura/sqlquery documentation](https://github.com/auraphp/Aura.SqlQuery/tree/3.x/docs).
