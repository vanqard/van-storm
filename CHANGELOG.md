# Changelog

All notable changes to this project are documented here. This project follows
[Semantic Versioning](https://semver.org/).

## [1.0.0] - Unreleased

First public release.

### Added
- Attribute-driven mapping with `#[Entity]` and `#[Column]`. Entities without attributes are
  mapped by naming convention.
- `MapperLocator`, which creates and caches one mapper per entity class, and `GenericMapper`
  for entities that don't declare their own mapper.
- `AbstractMapper`:
  - Finders: `find()` (including composite keys), `findBy()`, `findOneBy()`, `findAll()`,
    `findBySelect()` and `count()`. They return hydrated entities.
  - Writes: `addNew()`, `save()` and `remove()`.
  - Raw rows: `fetchOneRow()` and `fetchAllRows()`.
  - Table-scoped query builders: `newSelect()`, `newInsert()`, `newUpdate()` and
    `newDelete()`.
- Finder criteria accept property or column names, `null` (`IS NULL`) and arrays (`IN`).
  Identifiers are validated and quoted, and values are always bound.
- `TypeRegistry` with built-in `string`, `int`, `float`, `bool`, `datetime`, `object` and
  `json` types. Register your own with `register()`.
- `object` columns are serialized with zumba/json-serializer and rehydrate into their
  original classes. Only classes on an allow-list can be rehydrated. The list is **empty by
  default**, and violations throw `DisallowedClassException`.
- `vendor/bin/storm-allowlist` scans source directories for concrete classes and enums
  (without loading them) and writes the allow-list as a `require`-able PHP file. Configure
  it with `MapperLocator`'s `allowedClasses` option or `SerializerProxy::fromAllowListFile()`.
- Requires `zumba/json-serializer` 3.2.3 or later, which adds `setAllowedClasses()`
  (advisory PKSA-zhg7-9xn9-qv5j).
- `DateTimeType`, a `DateTime` with ISO 8601 serialization and bounds-checked setters.
- The SQL dialect is detected from the PDO driver (MySQL, SQLite, PostgreSQL, SQL Server).
- `StormExceptionInterface`, which every Storm exception implements.
