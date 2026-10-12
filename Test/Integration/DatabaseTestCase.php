<?php

declare(strict_types=1);

namespace Vanqard\Test\Integration;

use Aura\Sql\ExtendedPdo;
use PHPUnit\Framework\TestCase;
use Vanqard\Storm\App\MapperLocator;
use Vanqard\Test\Fixtures\Address;

/**
 * Runs against in-memory SQLite by default. Set STORM_TEST_DSN (plus
 * STORM_TEST_USER / STORM_TEST_PASSWORD) to run against MySQL instead.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected ExtendedPdo $pdo;

    protected MapperLocator $locator;

    protected function setUp(): void
    {
        $dsn = getenv('STORM_TEST_DSN') ?: 'sqlite::memory:';
        $this->pdo = new ExtendedPdo(
            $dsn,
            getenv('STORM_TEST_USER') ?: null,
            getenv('STORM_TEST_PASSWORD') ?: null,
        );

        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        self::assertIsString($driver);

        foreach ($this->schema($driver) as $statement) {
            $this->pdo->exec($statement);
        }

        $this->locator = new MapperLocator($this->pdo, allowedClasses: [Address::class]);
    }

    /**
     * @return list<string>
     */
    private function schema(string $driver): array
    {
        $autoId = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        return [
            'DROP TABLE IF EXISTS users',
            'DROP TABLE IF EXISTS convention_posts',
            'DROP TABLE IF EXISTS memberships',
            "CREATE TABLE users (
                id {$autoId},
                username VARCHAR(100) NOT NULL,
                email_address VARCHAR(255) NULL,
                is_active SMALLINT NOT NULL DEFAULT 1,
                created_at DATETIME NULL,
                address TEXT NULL,
                tags TEXT NULL
            )",
            "CREATE TABLE convention_posts (
                id {$autoId},
                post_title VARCHAR(255) NOT NULL,
                is_published SMALLINT NOT NULL DEFAULT 0,
                published_at DATETIME NULL,
                meta TEXT NULL
            )",
            'CREATE TABLE memberships (
                user_id INT NOT NULL,
                group_id INT NOT NULL,
                role VARCHAR(50) NOT NULL,
                PRIMARY KEY (user_id, group_id)
            )',
        ];
    }
}
