<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit;

use Doctrine\DBAL\Connection;

class DbConnection extends Connection
{
    public int $lastId = 0;

    public function lastInsertId($name = null): int
    {
        return $this->lastId;
    }
}
