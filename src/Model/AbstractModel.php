<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Exception\Exception;
use Xakki\Emailer\Helper;
use Xakki\Emailer\Repository\AbstractRepository;

abstract class AbstractModel
{
    public int $id;

    public const STATUS_ON = 'on';
    public const STATUS_OFF = 'off';

    /**
     * @param array<string,mixed> $input
     */
    final public function __construct(array $input = [])
    {
        $this->setProperties($input);
    }

    /**
     * @param array<string,string> $input
     * @return void
     */
    protected function setProperties(array $input): void
    {
        foreach ($input as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = $v;
            }
        }
    }

    /**
     * @param int $id
     * @param bool $selectForUpdate
     * @return static
     * @throws DataNotFound
     */
    public static function findOneById(int $id, bool $selectForUpdate = false): static
    {
        return static::findOne([static::getPkName() => $id], $selectForUpdate);
    }

    /**
     * @param array<string,string|int> $data
     * @param bool $selectForUpdate
     * @return static
     * @throws DataNotFound
     */
    public static function findOne(array $data, bool $selectForUpdate = false): static
    {
        $row = call_user_func([static::repositoryClass(), 'findOne'], $data, $selectForUpdate);
        if (!$row) {
            throw new DataNotFound('Not found data');
        }
        return new static($row);
    }

    public function lockForUpdate(): static
    {
        $row = call_user_func([static::repositoryClass(), 'findOne'], [self::getPkName() => self::getPk()], true);
        if (!$row) {
            throw new DataNotFound('Cant get lock');
        }
        $this->setProperties($row);
        return $this;
    }

    public static function getPkName(): string
    {
        return static::repositoryClass()::pkName();
    }

    /**
     * @param array<string,int|string> $data
     * @return static[]
     */
    public static function findAll(array $data): array
    {
        $rows = call_user_func([static::repositoryClass(), 'findAll'], $data);
        $res = [];
        foreach ($rows as $row) {
            $res[] = new static($row);
        }

        return $res;
    }

    /**
     * @param array<string,int|string> $data
     * @param array<string,string> $findData
     * @return static
     * @throws DataNotFound
     */
    public static function findOrSave(array $data, array $findData = []): static
    {
        try {
            $model = static::findOne(count($findData) ? $findData : $data);
        } catch (DataNotFound $e) {
            try {
                $model = new static($data);
                $model->insert();
            } catch (UniqueConstraintViolationException $e) {
                // Try again
                $model = static::findOne(count($findData) ? $findData : $data);
            }
        }

        return $model;
    }

    public function insert(): static
    {
        $data = $this->getProperties();
        /** @var AbstractRepository $rep */
        $rep = static::repositoryClass();
        $this->setPk($rep::insert($data));
        return $this;
    }

    public function setPk(int $val): static
    {
        $this->{static::getPkName()} = $val;
        return $this;
    }

    public function getPk(): int
    {
        return $this->{static::getPkName()};
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return Helper\Tools::getPublicProperty($this);
    }

    /**
     * @param string[] $fields
     * @return int
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(array $fields): int
    {
        if (!$this->id) {
            throw new Exception('Update only for saved model');
        }
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $this->{$field};
        }
        /** @var AbstractRepository $rep */
        $rep = static::repositoryClass();
        if (count($data)) {
            return $rep::updateById($this->id, $data);
        }
        return 0;
    }

    public function renew(): static
    {
        $row = call_user_func([static::repositoryClass(), 'findOne'], [self::getPkName() => self::getPk()]);
        if (!$row) {
            throw new DataNotFound('Not found data');
        }
        $this->setProperties($row);
        return $this;
    }

    /**
     * @return AbstractRepository
     * @phpstan-return class-string<AbstractRepository>
     */
    abstract protected static function repositoryClass(): string;
}
