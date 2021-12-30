<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Browser extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'browser';
    }
}
