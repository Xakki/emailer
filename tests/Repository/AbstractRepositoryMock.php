<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Repository;

use Xakki\Emailer\Repository\AbstractRepository;
use Xakki\Emailer\Tests\DbConnection;

class AbstractRepositoryMock extends AbstractRepository
{
    protected static DbConnection $db;
    public function __construct(DbConnection $db)
    {
        static::$db = $db;
    }

    protected static function tableName(): string
    {
        return 'test';
    }

    protected static function getDb(): DbConnection
    {
        return static::$db;
    }
}
