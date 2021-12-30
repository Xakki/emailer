<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

use Doctrine\DBAL\Types\Type;

abstract class AbstractRevision extends AbstractRepository
{
    private static bool $isRevisionMode = false;

//    protected static bool $revisionFieldChange = [];

    /**
     * @param int $id
     * @param array<string, mixed> $data
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     * @return int
     * @throws \Doctrine\DBAL\Exception
     * @throws \Xakki\Emailer\Exception\Exception
     */
    public static function updateById(int $id, array $data, array $types = []): int
    {
        $data['base_id'] = $id;
        self::$isRevisionMode = true;
        static::insert($data, $types);
        unset($data['base_id']);

        self::$isRevisionMode = false;
        return parent::updateById($id, $data, $types);
    }

    protected static function tableName(): string
    {
        return static::tableNameMain() . (self::$isRevisionMode ? '_rev' : '');
    }

    abstract protected static function tableNameMain(): string;
}
