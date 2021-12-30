<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Repository;

class QueueData extends AbstractModel
{
    public int $id;
    public string $data;
    public string $last_error;
    public int $transport_id;

    protected static function repositoryClass(): string
    {
        return Repository\QueueData::class;
    }
}
