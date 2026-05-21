<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Repository;

class QueueData extends AbstractModel
{
    public int $id;
    public string $data;
    // Nullable in schema (queue_data.last_error / transport_id).
    public ?string $last_error = null;
    public ?int $transport_id = null;

    protected static function repositoryClass(): string
    {
        return Repository\QueueData::class;
    }
}
