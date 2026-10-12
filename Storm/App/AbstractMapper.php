<?php

declare(strict_types=1);

namespace Vanqard\Storm\App;

use Aura\Sql\ExtendedPdo;
use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\InsertInterface;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use Aura\SqlQuery\Common\WhereInterface;
use Aura\SqlQuery\QueryFactory;
use DateTimeInterface;
use PDO;
use ReflectionNamedType;
use ReflectionProperty;
use Vanqard\Storm\Attributes\Entity;
use Vanqard\Storm\Exceptions\DisallowedClassException;
use Vanqard\Storm\Exceptions\InvalidMapperClassDeclaration;
use Vanqard\Storm\Exceptions\InvalidParameterException;
use Vanqard\Storm\Exceptions\InvalidPrimaryKeyDeclarationException;
use Vanqard\Storm\Interfaces\SerializerInterface;
use Vanqard\Storm\Interfaces\TypeCastInterface;
use Vanqard\Storm\Metadata\EntityMetadataFactory;
use Vanqard\Storm\Types\BooleanTypecast;
use Vanqard\Storm\Types\DateTimeType;
use Vanqard\Storm\Types\DateTimeTypecast;
use Vanqard\Storm\Types\FloatTypecast;
use Vanqard\Storm\Types\IntegerTypecast;
use Vanqard\Storm\Types\TypeRegistry;

/**
 * Base class for mappers. Mappers are created and configured by the
 * MapperLocator, so subclasses must be constructable without arguments.
 *
 * @template T of AbstractEntity
 */
abstract class AbstractMapper
{
    private const string IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    private const array DRIVER_DIALECTS = [
        'mysql' => 'mysql',
        'pgsql' => 'pgsql',
        'sqlite' => 'sqlite',
        'sqlsrv' => 'sqlsrv',
        'dblib' => 'sqlsrv',
    ];

    protected ExtendedPdo $connection;

    protected ?QueryFactory $queryFactory = null;

    protected ?SerializerInterface $serializer = null;

    protected ?EntityMetadataFactory $metadataFactory = null;

    protected ?TypeRegistry $typeRegistry = null;

    protected ?string $tableName = null;

    /**
     * @var class-string<T>|null
     */
    protected ?string $entityClassName = null;

    /**
     * Resolved typecasts per field name (null = naming conventions).
     *
     * @var array<string, TypeCastInterface|null>
     */
    private array $typecasts = [];

    /**
     * @var array<string, ReflectionProperty>
     */
    private array $reflectionProperties = [];

    // ------------------------------------------------------------------
    // Configuration
    // ------------------------------------------------------------------

    public function getConnection(): ExtendedPdo
    {
        return $this->connection;
    }

    public function setConnection(ExtendedPdo $connection): static
    {
        $this->connection = $connection;
        $this->queryFactory = null;
        return $this;
    }

    /**
     * Returns a query factory for the connection's SQL dialect, detected
     * lazily from the PDO driver name.
     */
    public function getQueryFactory(): QueryFactory
    {
        if ($this->queryFactory === null) {
            $driver = $this->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME);
            $dialect = is_string($driver) ? (self::DRIVER_DIALECTS[$driver] ?? 'common') : 'common';
            $this->queryFactory = new QueryFactory($dialect);
        }

        return $this->queryFactory;
    }

    public function setQueryFactory(QueryFactory $queryFactory): static
    {
        $this->queryFactory = $queryFactory;
        return $this;
    }

    public function setSerializer(SerializerInterface $serializer): static
    {
        $this->serializer = $serializer;
        return $this;
    }

    public function getSerializer(): SerializerInterface
    {
        return $this->serializer ??= new SerializerProxy();
    }

    public function setMetadataFactory(EntityMetadataFactory $metadataFactory): static
    {
        $this->metadataFactory = $metadataFactory;
        return $this;
    }

    public function getMetadataFactory(): EntityMetadataFactory
    {
        return $this->metadataFactory ??= new EntityMetadataFactory();
    }

    public function setTypeRegistry(TypeRegistry $typeRegistry): static
    {
        $this->typeRegistry = $typeRegistry;
        $this->typecasts = [];
        return $this;
    }

    public function getTypeRegistry(): TypeRegistry
    {
        return $this->typeRegistry ??= new TypeRegistry($this->getSerializer());
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    public function setTableName(string $tableName): static
    {
        $this->tableName = $tableName;
        return $this;
    }

    /**
     * @return class-string<T>|null
     */
    public function getEntityClassName(): ?string
    {
        return $this->entityClassName;
    }

    /**
     * @param class-string<T>|null $entityClassName
     */
    public function setEntityClassName(?string $entityClassName): static
    {
        $this->entityClassName = $entityClassName;
        $this->typecasts = [];
        $this->reflectionProperties = [];
        return $this;
    }

    /**
     * @throws InvalidMapperClassDeclaration when no entity class has been set
     */
    public function getMetadata(): Entity
    {
        return $this->getMetadataFactory()->getMetadata($this->requireEntityClassName());
    }

    /**
     * @return class-string<T>
     * @throws InvalidMapperClassDeclaration when no entity class has been set
     */
    private function requireEntityClassName(): string
    {
        if ($this->entityClassName === null) {
            throw new InvalidMapperClassDeclaration(
                sprintf("The mapper '%s' has not been given an entity class", static::class)
            );
        }

        return $this->entityClassName;
    }

    // ------------------------------------------------------------------
    // Query builders, scoped to this mapper's table
    // ------------------------------------------------------------------

    public function newSelect(): SelectInterface
    {
        $select = $this->getQueryFactory()->newSelect();
        $select->from($this->resolveTableName());
        return $select;
    }

    public function newInsert(): InsertInterface
    {
        $insert = $this->getQueryFactory()->newInsert();
        $insert->into($this->resolveTableName());
        return $insert;
    }

    public function newUpdate(): UpdateInterface
    {
        $update = $this->getQueryFactory()->newUpdate();
        $update->table($this->resolveTableName());
        return $update;
    }

    public function newDelete(): DeleteInterface
    {
        $delete = $this->getQueryFactory()->newDelete();
        $delete->from($this->resolveTableName());
        return $delete;
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Finds an entity by primary key. For composite keys pass an array keyed
     * by field name.
     *
     * @return T|null
     */
    public function find(mixed $id): ?AbstractEntity
    {
        $pkFields = $this->getMetadata()->getPrimaryKeyFieldsAsList();

        if (is_array($id)) {
            $criteria = $id;
        } elseif (count($pkFields) === 1) {
            $criteria = [$pkFields[0] => $id];
        } else {
            throw new InvalidParameterException(
                'This entity has a composite primary key; pass an array of key values to find()'
            );
        }

        return $this->findOneBy($criteria);
    }

    /**
     * Finds entities matching every criterion. Keys may be property names or
     * column names; a null value matches IS NULL and an array value matches IN.
     *
     * @param array<string, mixed> $criteria
     * @param array<int|string, string>|null $orderBy ['field' => 'ASC'|'DESC'] or ['field', ...]
     * @return list<T>
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $select = $this->newSelect()->cols(['*']);
        $this->applyCriteria($select, $criteria);

        if ($orderBy !== null) {
            $select->orderBy($this->buildOrderBy($orderBy));
        }

        if ($limit !== null) {
            $select->limit($limit);
        }

        if ($offset !== null) {
            $select->offset($offset);
        }

        return $this->findBySelect($select);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<int|string, string>|null $orderBy
     * @return T|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?AbstractEntity
    {
        return $this->findBy($criteria, $orderBy, 1)[0] ?? null;
    }

    /**
     * @return list<T>
     */
    public function findAll(): array
    {
        return $this->findBy([]);
    }

    /**
     * Hydrates every row returned by a custom select.
     *
     * @return list<T>
     */
    public function findBySelect(SelectInterface $select): array
    {
        return array_map(
            fn (array $row): AbstractEntity => $this->hydrate($row),
            $this->fetchAllRows($select)
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int
    {
        $select = $this->newSelect()->cols(['COUNT(*) AS num']);
        $this->applyCriteria($select, $criteria);

        return (int) $this->getConnection()->fetchValue($select->getStatement(), $select->getBindValues());
    }

    /**
     * Returns the first raw row of a custom select, or null.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOneRow(SelectInterface $select): ?array
    {
        $row = $this->getConnection()->fetchOne($select->getStatement(), $select->getBindValues());
        return $row === false ? null : $row;
    }

    /**
     * Returns all raw rows of a custom select.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAllRows(SelectInterface $select): array
    {
        /** @var list<array<string, mixed>> */
        return $this->getConnection()->fetchAll($select->getStatement(), $select->getBindValues());
    }

    /**
     * Runs an insert, update or delete built with newInsert()/newUpdate()/newDelete()
     * and returns the number of affected rows. Bound values are passed through
     * as-is: Storm's typecasts are not applied.
     */
    public function execute(InsertInterface|UpdateInterface|DeleteInterface $query): int
    {
        return $this->getConnection()->fetchAffected($query->getStatement(), $query->getBindValues());
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Inserts the entity. An empty (null or 0) auto-incrementing primary key is
     * left to the database and populated on the entity afterwards.
     *
     * @param T $entity
     * @return T
     */
    public function addNew(AbstractEntity $entity): AbstractEntity
    {
        $metadata = $this->getMetadata();
        $data = $this->extract($entity);

        $autoField = $metadata->hasAutoIncrementingPrimaryKey()
            ? $metadata->getAutoIncrementingPrimaryKeyFieldName()
            : null;
        $autoColumn = $autoField === null ? null : $metadata->getColumnNameForFieldName($autoField);

        if ($autoColumn !== null && empty($data[$autoColumn])) {
            unset($data[$autoColumn]);
        }

        $insert = $this->newInsert()->cols($data);
        $this->getConnection()->perform($insert->getStatement(), $insert->getBindValues());

        if ($autoField !== null && $autoColumn !== null && !array_key_exists($autoColumn, $data)) {
            $id = $this->getConnection()->lastInsertId($insert->getLastInsertIdName($autoColumn));
            if ($id !== false) {
                $value = is_numeric($id) ? (int) $id : $id;
                $this->property($autoField)->setValue($entity, $this->toPhpValue($autoField, $value));
            }
        }

        return $entity;
    }

    /**
     * Updates every mapped column of an existing entity, matched by primary key.
     *
     * @param T $entity
     * @return T
     * @throws InvalidPrimaryKeyDeclarationException when the primary key is not set
     */
    public function save(AbstractEntity $entity): AbstractEntity
    {
        $data = $this->extract($entity);
        $keyCriteria = $this->primaryKeyCriteria($entity);

        foreach ($this->getMetadata()->getPrimaryKeyColumnsAsList() as $pkColumn) {
            unset($data[$pkColumn]);
        }

        if ($data === []) {
            return $entity;
        }

        $update = $this->newUpdate()->cols($data);
        $this->applyCriteria($update, $keyCriteria);
        $this->getConnection()->perform($update->getStatement(), $update->getBindValues());

        return $entity;
    }

    /**
     * Deletes the entity's row, matched by primary key.
     *
     * @param T $entity
     * @return bool true when a row was deleted
     */
    public function remove(AbstractEntity $entity): bool
    {
        $delete = $this->newDelete();
        $this->applyCriteria($delete, $this->primaryKeyCriteria($entity));

        return $this->execute($delete) > 0;
    }

    // ------------------------------------------------------------------
    // Hydration
    // ------------------------------------------------------------------

    /**
     * Creates an entity from a database row. Columns that are not mapped to a
     * property are ignored. The entity constructor is not called.
     *
     * @param array<string, mixed> $row
     * @return T
     */
    public function hydrate(array $row): AbstractEntity
    {
        $metadata = $this->getMetadata();
        $entity = (new \ReflectionClass($this->requireEntityClassName()))->newInstanceWithoutConstructor();

        foreach ($row as $columnName => $value) {
            try {
                $fieldName = $metadata->getFieldNameForColumnName((string) $columnName);
            } catch (InvalidParameterException) {
                continue;
            }

            $this->property($fieldName)->setValue($entity, $this->toPhpValue($fieldName, $value));
        }

        return $entity;
    }

    /**
     * Converts an entity into a row of database values keyed by column name.
     * Uninitialized typed properties are omitted.
     *
     * @param T $entity
     * @return array<string, mixed>
     */
    public function extract(AbstractEntity $entity): array
    {
        $row = [];

        foreach ($this->getMetadata()->getColumns() as $fieldName => $column) {
            $property = $this->property($fieldName);
            if (!$property->isInitialized($entity)) {
                continue;
            }

            $row[$column->getName()] = $this->toDbValue($fieldName, $property->getValue($entity));
        }

        return $row;
    }

    /**
     * Converts property-name-keyed values to column-keyed database values using
     * Storm's naming conventions.
     *
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    public function toDbProperties(array $properties): array
    {
        $retArray = [];
        foreach ($properties as $name => $value) {
            $retArray[EntityMetadataFactory::toColumnName($name)] = $this->conventionToDb($value);
        }

        return $retArray;
    }

    /**
     * Converts a column-keyed database row to property-keyed values using
     * Storm's naming conventions.
     *
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    public function toEntityProperties(array $properties): array
    {
        $retArray = [];
        foreach ($properties as $name => $value) {
            $key = EntityMetadataFactory::toFieldName($name);
            $retArray[$key] = $this->conventionToPhp($name, $key, $value);
        }

        return $retArray;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function resolveTableName(): string
    {
        return $this->tableName ?? $this->getMetadata()->getTable();
    }

    private function property(string $fieldName): ReflectionProperty
    {
        return $this->reflectionProperties[$fieldName]
            ??= new ReflectionProperty($this->requireEntityClassName(), $fieldName);
    }

    /**
     * The typecast for a field: the registered type declared on its #[Column],
     * else one inferred from the property's declared type, else null (use the
     * naming conventions).
     */
    private function typecastFor(string $fieldName): ?TypeCastInterface
    {
        if (array_key_exists($fieldName, $this->typecasts)) {
            return $this->typecasts[$fieldName];
        }

        $type = $this->getMetadata()->getColumnSpecForFieldName($fieldName)->getType();

        return $this->typecasts[$fieldName] = $type !== ''
            ? $this->getTypeRegistry()->get($type)
            : $this->inferTypecast($this->property($fieldName));
    }

    private function inferTypecast(ReflectionProperty $property): ?TypeCastInterface
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        return match (true) {
            $type->getName() === 'int' => new IntegerTypecast(),
            $type->getName() === 'float' => new FloatTypecast(),
            $type->getName() === 'bool' => new BooleanTypecast(),
            in_array($type->getName(), [DateTimeType::class, \DateTime::class, DateTimeInterface::class], true)
                => new DateTimeTypecast(),
            default => null,
        };
    }

    private function toDbValue(string $fieldName, mixed $value): mixed
    {
        $typecast = $this->typecastFor($fieldName);
        return $typecast !== null ? $typecast->getDatabaseValue($value) : $this->conventionToDb($value);
    }

    private function toPhpValue(string $fieldName, mixed $value): mixed
    {
        $typecast = $this->typecastFor($fieldName);
        if ($typecast !== null) {
            return $typecast->getPhpValue($value);
        }

        $columnName = $this->getMetadata()->getColumnNameForFieldName($fieldName);
        return $this->conventionToPhp($columnName, $fieldName, $value);
    }

    private function conventionToDb(mixed $value): mixed
    {
        // Dates must be handled before the generic object case
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeType::DATABASE_FORMAT);
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_object($value) || is_array($value)) {
            return $this->getSerializer()->serialize($value);
        }

        return $value;
    }

    private function conventionToPhp(string $columnName, string $fieldName, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && str_contains($value, '"@type"') && in_array($value[0] ?? '', ['{', '['], true)) {
            try {
                return $this->getSerializer()->unserialize($value);
            } catch (DisallowedClassException $e) {
                throw $e;
            } catch (\Throwable) {
                return $value;
            }
        }

        if (is_string($value) && str_ends_with($fieldName, 'At')) {
            try {
                return new DateTimeType($value);
            } catch (\Exception) {
                return $value;
            }
        }

        if (str_starts_with($columnName, 'is_') || str_starts_with($columnName, 'has_') || str_starts_with($columnName, 'can_')) {
            return (bool) $value;
        }

        return $value;
    }

    /**
     * @param T $entity
     * @return array<string, mixed>
     */
    private function primaryKeyCriteria(AbstractEntity $entity): array
    {
        $criteria = [];

        foreach ($this->getMetadata()->getPrimaryKeyFieldsAsList() as $fieldName) {
            $property = $this->property($fieldName);
            $value = $property->isInitialized($entity) ? $property->getValue($entity) : null;

            if ($value === null || $value === '' || $value === 0) {
                throw new InvalidPrimaryKeyDeclarationException(
                    sprintf("The primary key field '%s' must be set to save or remove an entity", $fieldName)
                );
            }

            $criteria[$fieldName] = $value;
        }

        return $criteria;
    }

    /**
     * Adds WHERE conditions with bound values. Identifiers are validated and
     * quoted; values are never interpolated.
     *
     * @param array<string, mixed> $criteria
     */
    private function applyCriteria(WhereInterface $query, array $criteria): void
    {
        $index = 0;
        foreach ($criteria as $key => $value) {
            [$column, $fieldName] = $this->resolveColumn((string) $key);
            $quoted = $this->getConnection()->quoteName($column);

            if ($value === null) {
                $query->where("{$quoted} IS NULL");
                continue;
            }

            $placeholder = 'storm_w' . $index++;

            if (is_array($value)) {
                if ($value === []) {
                    // An empty IN () list matches nothing
                    $query->where('1 = 0');
                    continue;
                }

                $value = array_values(array_map(
                    fn (mixed $v): mixed => $fieldName !== null ? $this->toDbValue($fieldName, $v) : $v,
                    $value
                ));
                $query->where("{$quoted} IN (:{$placeholder})", [$placeholder => $value]);
                continue;
            }

            $dbValue = $fieldName !== null ? $this->toDbValue($fieldName, $value) : $this->conventionToDb($value);
            $query->where("{$quoted} = :{$placeholder}", [$placeholder => $dbValue]);
        }
    }

    /**
     * @param array<int|string, string> $orderBy
     * @return list<string>
     */
    private function buildOrderBy(array $orderBy): array
    {
        $clauses = [];
        foreach ($orderBy as $key => $direction) {
            if (is_int($key)) {
                $key = $direction;
                $direction = 'ASC';
            }

            $direction = strtoupper(trim($direction));
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new InvalidParameterException(
                    sprintf("Invalid sort direction '%s'; expected ASC or DESC", $direction)
                );
            }

            [$column] = $this->resolveColumn($key);
            $clauses[] = $this->getConnection()->quoteName($column) . ' ' . $direction;
        }

        return $clauses;
    }

    /**
     * Resolves a criteria key (property or column name) to a column name.
     * Unmapped keys are accepted only if they are plain SQL identifiers.
     *
     * @return array{0: string, 1: string|null} [column name, field name if mapped]
     */
    private function resolveColumn(string $key): array
    {
        $metadata = $this->getMetadata();

        $columns = $metadata->getColumns();
        if (isset($columns[$key])) {
            return [$columns[$key]->getName(), $key];
        }

        foreach ($columns as $fieldName => $column) {
            if ($column->getName() === $key) {
                return [$key, $fieldName];
            }
        }

        if (preg_match(self::IDENTIFIER_PATTERN, $key) !== 1) {
            throw new InvalidParameterException(sprintf("'%s' is not a valid column name", $key));
        }

        return [$key, null];
    }
}
