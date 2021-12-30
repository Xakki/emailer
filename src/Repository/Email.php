<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Email extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'email';
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
            'domain_id' => static::TYPE_INT,
            'parent' => static::TYPE_INT,
            'cnt_send' => static::TYPE_INT,
            'cnt_read' => static::TYPE_INT,
            'project_id' => static::TYPE_INT,
        ];
    }
}
