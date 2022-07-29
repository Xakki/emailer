<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Emailer;
use Xakki\Emailer\Repository;
use Xakki\Emailer\Transports\AbstractTransport;

class Transport extends AbstractModel
{
    public const STATUS_ON = 'on';
    public const STATUS_OFF = 'off';

    public int $id;
    public string $status;
    public string $params;
    public string $created;
    public int $limit_day;
    public int $cnt_day;
    public ?int $domain_id;
    public int $project_id;

    protected static function repositoryClass(): string
    {
        return Repository\Transport::class;
    }

    public function getSmtpTransport(Emailer $emailer): AbstractTransport
    {
        return AbstractTransport::fromString($this->params, $emailer);
    }

    public function incCntDay(): self
    {
        $this->cnt_day++;
        Repository\Transport::inc($this->id, 'cnt_day');
        return $this;
    }
}
