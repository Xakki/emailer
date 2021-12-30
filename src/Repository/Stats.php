<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Stats extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'stats';
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
            'project_id' => static::TYPE_INT,
            'queue_id' => static::TYPE_INT,
            'domain_id' => static::TYPE_INT,
            'browser_id' => static::TYPE_INT,
            'action' => static::TYPE_INT,
        ];
    }
}
