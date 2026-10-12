# Getting started

## Install

```bash
composer require vanqard/van-storm
```

You need PHP 8.4 or later with `ext-pdo` and the PDO driver for your database. MySQL is the
primary target. SQLite, PostgreSQL and SQL Server also work, because Storm picks the SQL
dialect from the connection's PDO driver.

## Define an entity

```php
use Vanqard\Storm\App\AbstractEntity;
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

    #[Column(type: 'bool')]
    public bool $isActive = true;              // column: is_active

    #[Column(type: 'datetime')]
    public ?DateTimeType $createdAt = null;    // column: created_at

    #[Column(type: 'object', nullable: true)]
    public ?Address $address = null;           // stored as JSON text, rehydrated as Address
}
```

## Allow the classes stored in `object` columns

`Address` is stored in an `object` column, so Storm needs to know that it's safe to
rehydrate. Generate the allow-list from your source tree:

```bash
vendor/bin/storm-allowlist --output=config/storm-allowed-classes.php src/Model
```

Without this step, storing or reading `$address` throws `DisallowedClassException`. See
[Types](types.md#the-class-allow-list).

## Connect and use a mapper

```php
use Aura\Sql\ExtendedPdo;
use Vanqard\Storm\App\MapperLocator;

$pdo = new ExtendedPdo('mysql:host=localhost;dbname=app', 'user', 'secret');
$storm = new MapperLocator($pdo, allowedClasses: __DIR__ . '/config/storm-allowed-classes.php');

$users = $storm->mapper(User::class);

$user = new User();
$user->username = 'ada';
$user->createdAt = new DateTimeType();
$user->address = new Address('1 Analytical St', 'London');

$users->addNew($user);          // $user->id is populated afterwards

$found = $users->find($user->id);           // ?User
$found->address instanceof Address;          // true

$found->isActive = false;
$users->save($found);

$active = $users->findBy(['isActive' => true], ['username' => 'ASC'], limit: 10);
$total = $users->count();

$users->remove($found);
```

The `MapperLocator` creates one mapper for each entity class and caches it. An entity that
doesn't declare a mapper class gets a `GenericMapper`. To add your own finders, see
[Mappers](mappers.md#custom-mappers).
