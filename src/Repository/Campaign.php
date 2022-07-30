<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Campaign extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'campaign';
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
            'transport_id' => static::TYPE_INT,
            'notify_id' => static::TYPE_INT,
            'tpl_wrapper_id' => static::TYPE_INT,
            'tpl_content_id' => static::TYPE_INT,
            'project_id' => static::TYPE_INT,
            'cnt_send' => static::TYPE_INT,
            'cnt_queue' => static::TYPE_INT,
            'limit_day' => static::TYPE_INT,
        ];
    }
}
