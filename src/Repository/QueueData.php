<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class QueueData extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'queue_data';
    }
}
