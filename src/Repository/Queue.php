<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Queue extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'queue';
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
            'status' => static::TYPE_INT,
            'retry' => static::TYPE_INT,
            'project_id' => static::TYPE_INT,
            'campany_id' => static::TYPE_INT,
            'notify_id' => static::TYPE_INT,
            'email_id' => static::TYPE_INT,
        ];
    }
}
