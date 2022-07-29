<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Generator;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception;

abstract class AbstractRepository
{
    public const TYPE_INT = 1;
    public const TYPE_FLOAT = 2;
    /** @var array<string,mixed> */
    protected array $data = [];

    abstract protected static function tableName(): string;

    protected static function getDb(): Connection
    {
        return Emailer::i()->getDb();
    }

    /**
     * Insert or Update
     *
     * @param array<string, mixed> $data
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     * @return int ID
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception\Exception
     */
    public static function save(array $data = [], array $types = []): int
    {
        $pk = static::pkName();
        if (empty($data[$pk])) {
            $id = static::insert($data, $types);
        } else {
            $id = $data[$pk];
            unset($data[$pk]);
            static::updateById($id, $data, $types);
        }
        return $id;
    }

    public static function pkName(): string
    {
        return 'id';
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     * @return int Id
     * @throws Exception\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function insert(array $data, array $types = []): int
    {
        $id = null;
        $values = [];

        foreach ($data as $name => $val) {
            if ($val === null) {
                continue;
            }
            if ($val instanceof expresion\NullExpresion) {
                $val = null;
            }
            $values[$name] = $val;
        }
        $res = static::getDb()->insert(static::tableName(), $values, $types);

        if ($res) {
            $id = $values[static::pkName()] ?? (int)static::getDb()->lastInsertId();
        }

        Emailer::i()->getLogger()->debug('INSERT INTO `' . static::tableName() . '` => #' . $id . '.', ['insert', 'data' => $values]);

        if (!$id) {
            throw new Exception\Exception('Cant insert data');
        }

        return $id;
    }

    /**
     * @param int $id
     * @param array<string, mixed> $data
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     * @return int Count
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception\Exception
     */
    public static function updateById(int $id, array $data, array $types = []): int
    {
        $values = [];

        foreach ($data as $name => $val) {
            if ($val === null) {
                continue;
            }
            if ($val instanceof expresion\NullExpresion) {
                $val = null;
            }
            $values[$name] = $val;
        }

        $cnt = static::getDb()->update(static::tableName(), $values, [static::pkName() => $id], $types);

        Emailer::i()->getLogger()->debug('UPDATE `' . static::tableName() . '` => affected rows ' . $cnt . '.', ['update', 'data' => $values]);

        return $cnt;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string|int, mixed> $criteria
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     * @return int
     * @throws \Doctrine\DBAL\Exception
     */
    public static function update(array $data, array $criteria, array $types = []): int
    {
        $cnt = static::getDb()->update(static::tableName(), $data, $criteria, $types);

        Emailer::i()->getLogger()->debug('UPDATE `' . static::tableName() . '` => affected rows ' . $cnt . '.', ['update', 'data' => $data, 'criteria' => $criteria]);

        return $cnt;
    }

    public static function inc(int $id, string $field, int $val = 1): int
    {
        return self::getDb()->executeStatement('UPDATE ' . static::tableName() . ' SET ' . $field . ' = ' . $field . ' + ? WHERE id = ?', [$val, $id]);
    }

    /**
     * @param array<string, mixed> $data
     * @param bool $selectForUpdate
     * @return array<string,mixed>
     * @throws Exception\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function findOne(array $data, bool $selectForUpdate = false): array
    {
        $query = self::createQueryBuilder();

        foreach ($data as $k => $val) {
            if (is_array($val)) {
                $query->andWhere($k . ' IN (:' . $k . ')');
                $query->setParameter($k, $val, 'array');
            } else {
                $query->andWhere($k . '=:' . $k);
                $query->setParameter($k, $val);
            }
        }

        return static::getRowByQuery($query, $selectForUpdate);
    }

    /**
     * @return QueryBuilder
     * @throws Exception\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function createQueryBuilder(): QueryBuilder
    {
        return self::getDb()->createQueryBuilder()
            ->select('*')
            ->from(static::tableName());
    }

    /**
     * @param QueryBuilder $query
     * @param bool $selectForUpdate
     * @return array<string,mixed>
     * @throws Exception\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function getRowByQuery(QueryBuilder $query, bool $selectForUpdate = false): array
    {
        $query->setMaxResults(1);
        $q = $query->getSQL();
        if ($selectForUpdate) {
            $q .= ' FOR UPDATE';
        }

        Emailer::i()->getLogger()->debug($q, ['query']);

        $row = self::getDb()->fetchAssociative($q, $query->getParameters(), $query->getParameterTypes());
        if ($row) {
            return static::validate($row);
        }
        return [];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function validate(array $row): array
    {
        foreach (static::getRules() as $field => $rule) {
            if (isset($row[$field])) {
                switch ($rule) {
                    case static::TYPE_INT:
                        $row[$field] = intval($row[$field]);
                        break;
                    case static::TYPE_FLOAT:
                        $row[$field] = floatval($row[$field]);
                        break;
                    default:
                }
            }
        }
        return $row;
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return Generator
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception\Exception
     */
    public static function findAll(array $data): Generator
    {
        $query = self::createQueryBuilder();

        foreach ($data as $k => $val) {
            $query->andWhere($k . '=:' . $k);
            $query->setParameter($k, $val);
        }

        return static::getModelsByQuery($query);
    }

    /**
     * @param QueryBuilder $query
     * @return Generator
     * @throws \Doctrine\DBAL\Exception
     */
    public static function getModelsByQuery(QueryBuilder $query): Generator
    {
        Emailer::i()->getLogger()->debug($query->getSQL(), ['query']);
        $result = $query->executeQuery();
        while ($row = $result->fetchAssociative()) {
            yield static::validate($row);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param bool $selectForUpdate
     * @return int
     * @throws Exception\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function findId(array $data, bool $selectForUpdate = false): int
    {
        $query = self::createQueryBuilder();

        foreach ($data as $k => $val) {
            $query->andWhere($k . '=:' . $k);
            $query->setParameter($k, $val);
        }
        $query->select(self::pkName());
        $row = static::getRowByQuery($query, $selectForUpdate);
        return $row[self::pkName()] ?? 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return \Doctrine\DBAL\Result
     * @throws \Doctrine\DBAL\Exception
     */
    public static function delete(array $data)
    {
        $query = self::getDb()->createQueryBuilder()
            ->delete(static::tableName());

        foreach ($data as $k => $val) {
            $query->andWhere($k . '=:' . $k);
            $query->setParameter($k, $val);
        }
        Emailer::i()->getLogger()->debug($query->getSQL(), ['delete']);
        return $query->executeQuery();
    }
}
