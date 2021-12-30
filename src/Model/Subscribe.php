<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Repository;

class Subscribe extends AbstractModel
{
    public const STATUS_ON = 'on';
    public const STATUS_OFF = 'off';

    public int $id;
    public int $notify_id;
    public int $email_id;
    public int $project_id;
    public string $created;
    public int $period;
    public string $status;

    protected static function repositoryClass(): string
    {
        return Repository\Subscribe::class;
    }
}
