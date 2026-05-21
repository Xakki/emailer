<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests;

use Doctrine\DBAL\Connection;

class DbConnection extends Connection
{
    public int $lastId = 0;

    // DBAL 4 dropped the $name argument and widened the return type to int|string.
    public function lastInsertId(): int|string
    {
        return $this->lastId;
    }
}
