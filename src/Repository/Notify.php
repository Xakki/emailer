<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Notify extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'notify';
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
            'project_id' => static::TYPE_INT,
        ];
    }
}
