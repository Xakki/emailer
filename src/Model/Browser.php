<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Repository;

class Browser extends AbstractModel
{
    public int $id;
    public string $ua;

    protected static function repositoryClass(): string
    {
        return Repository\Browser::class;
    }
}
