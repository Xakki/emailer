<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Subscribe extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'subscribe';
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
            'notify_id' => static::TYPE_INT,
            'email_id' => static::TYPE_INT,
            'project_id' => static::TYPE_INT,
            'period' => static::TYPE_INT,
        ];
    }
}
