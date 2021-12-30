<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Domain extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'domain';
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
            'parent' => static::TYPE_INT,
        ];
    }
}
