<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Repository;

class Template extends AbstractModel
{
    public const TYPE_WRAPER = 'wraper';
    public const TYPE_CONTENT = 'content';
    public const TYPE_BLOCK = 'block';

    public const NAME_HOST = 'project.host';
    public const NAME_PROJECT = 'project.name';
    public const NAME_URL = 'project.url';
    public const NAME_URL_LOGO = 'project.logo';

    public const NAME_ROUTE = 'route';
    public const NAME_REPLY = 'reply';
    public const NAME_TIMEZONE = 'timezone';
    public const NAME_YEAR = 'year';
    public const NAME_LANG = 'lang';

    public const NAME_URL_UNSUBSCRIBE = 'unsubscribe.url';
    public const NAME_URL_SUBSCRIBE = 'subscribe.url';

    public const NAME_TITLE = 'mail.title';
    public const NAME_DESCR = 'mail.descr';
    public const NAME_NOTIFY = 'mail.notify';

    public int $id;
    public string $created;
    public string $name;
    public int $project_id;
    public string $html;
    public string $type;
    public int $base_id;

    protected static function repositoryClass(): string
    {
        return Repository\Template::class;
    }
}
