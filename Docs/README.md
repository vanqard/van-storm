# Storm documentation

Storm is a small data-mapper ORM for PHP 8.4+. It sits on top of
[aura/sql](https://github.com/auraphp/Aura.Sql) (connections) and
[aura/sqlquery](https://github.com/auraphp/Aura.SqlQuery) (query building).

- [Getting started](getting-started.md): install, connect, and run your first round trip
- [Entities](entities.md): declaring entities with `#[Entity]` / `#[Column]`, or by convention
- [Mappers](mappers.md): finding, saving and removing entities, custom finders
- [Query builder](query-builder.md): hand-built selects, joins, paging, aggregates, bulk writes, transactions
- [Types](types.md): built-in column types, `DateTimeType`, and registering your own types
- [Exceptions](exceptions.md): what Storm throws and when
