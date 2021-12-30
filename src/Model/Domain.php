<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Repository;

class Domain extends AbstractModel
{
    public const STATUS_BAD = 'bad';
    public const STATUS_GOOD = 'good';

    public int $id;
    public string $name;
    public string $mx;
    public string $https;
    public string $status;
    public int $parent;
    public string $created;

    protected static function repositoryClass(): string
    {
        return Repository\Domain::class;
    }
}
