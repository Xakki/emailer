<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Repository;

class Notify extends AbstractModel
{
    public int $id;
    public string $created;
    public string $name;
    public int $project_id;

    protected static function repositoryClass(): string
    {
        return Repository\Notify::class;
    }
}
