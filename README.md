# Storm

[![CI](https://github.com/vanqard/van-storm/actions/workflows/ci.yml/badge.svg)](https://github.com/vanqard/van-storm/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/vanqard/van-storm.svg)](https://packagist.org/packages/vanqard/van-storm)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A small data-mapper ORM for PHP 8.4+, built around the awesome [aura/sql](https://github.com/auraphp/Aura.Sql)
and [aura/sqlquery](https://github.com/auraphp/Aura.SqlQuery) packages.

- **Attribute-driven mapping.** Use `#[Entity]` and `#[Column]` on plain PHP classes, or let
  Storm map them by naming convention.
- **A reusable type library.** Built-in `int`, `float`, `bool`, `string`, `datetime`, `json`
  and `object` types, and you can register your own.
- **Objects that come back as objects.** An `object` column is stored as JSON text and
  rehydrates into its original class, nested objects included. Only classes on an
  allow-list can be rehydrated, and a bundled script generates that list.
- **Safe finders.** Criteria and sort keys are validated and quoted, and values are always
  bound.
- **Several databases.** MySQL is the primary target. SQLite, PostgreSQL and SQL Server work
  through dialect detection from the PDO driver.

## Installation

```bash
composer require vanqard/van-storm
```

Requires PHP 8.4+ and `ext-pdo`.

## Quick start

```php
use Aura\Sql\ExtendedPdo;
use Vanqard\Storm\App\AbstractEntity;
use Vanqard\Storm\App\MapperLocator;
use Vanqard\Storm\Attributes\Column;
use Vanqard\Storm\Attributes\Entity;
use Vanqard\Storm\Types\DateTimeType;

#[Entity(table: 'users')]
class User extends AbstractEntity
{
    #[Column(type: 'int', primary: true, autoincrement: true)]
    public ?int $id = null;

    #[Column(type: 'string')]
    public string $username = '';

    #[Column(type: 'datetime')]
    public ?DateTimeType $createdAt = null;

    #[Column(type: 'object', nullable: true)]
    public ?Preferences $preferences = null;
}

// Classes that `object` columns may rehydrate (see "Object columns" below)
$storm = new MapperLocator(
    new ExtendedPdo('mysql:host=localhost;dbname=app', 'user', 'secret'),
    allowedClasses: __DIR__ . '/config/storm-allowed-classes.php',
);
$users = $storm->mapper(User::class);

$user = new User();
$user->username = 'ada';
$user->createdAt = new DateTimeType();
$user->preferences = new Preferences(theme: 'dark');
$users->addNew($user);                         // $user->id is now set

$same = $users->find($user->id);               // ?User, with ->preferences rehydrated as Preferences
$list = $users->findBy(['username' => ['ada', 'grace']], ['createdAt' => 'DESC'], limit: 20);

$same->username = 'ada.l';
$users->save($same);
$users->remove($same);
```

## Object columns and the class allow-list

Rehydrating objects from stored JSON is only safe for classes you expect, so Storm uses
an allow-list that is **empty by default**. Generate it from your source tree, commit it,
and regenerate it when you add value objects:

```bash
vendor/bin/storm-allowlist --output=config/storm-allowed-classes.php src/Model
```

See [Types](Docs/types.md#the-class-allow-list) for details.

## Documentation

- [Getting started](Docs/getting-started.md)
- [Entities](Docs/entities.md)
- [Mappers](Docs/mappers.md)
- [Query builder](Docs/query-builder.md)
- [Types and custom types](Docs/types.md)
- [Exceptions](Docs/exceptions.md)

## Development

```bash
composer install
composer test        # PHPUnit: unit tests + SQLite integration tests
composer analyse     # PHPStan, level 8
composer cs          # PHP-CS-Fixer (PSR-12) dry run; `composer cs-fix` to apply
```

To run the integration tests against MySQL, set `STORM_TEST_DSN`, `STORM_TEST_USER` and
`STORM_TEST_PASSWORD`.

## Security

See [SECURITY.md](SECURITY.md) for how to report vulnerabilities.

## License

MIT. See [LICENSE](LICENSE).
