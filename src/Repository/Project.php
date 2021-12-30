<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Project extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'project';
    }
}
