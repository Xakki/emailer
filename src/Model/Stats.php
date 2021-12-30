<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Repository;

class Stats extends AbstractModel
{
    public const ACTION_READ = 0,
        ACTION_HOME = 1,
        ACTION_TODO = 2,
        ACTION_UNSUB = 3,
        ACTION_SUBS = 4,
        ACTION_GOTO = 5,
        ACTION_STATUS = 5;

    public int $created;
    public int $project_id;
    public int $queue_id;
    public string $uri_ref;
    public int $domain_id;
    public int $browser_id;
    public int $action;

    protected static function repositoryClass(): string
    {
        return Repository\Stats::class;
    }
}
