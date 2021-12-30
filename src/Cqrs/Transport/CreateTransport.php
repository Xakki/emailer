<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Transport;

use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Model\Transport;
use Xakki\Emailer\Transports\AbstractTransport as TransportBase;

class CreateTransport
{
    protected int $projectId;
    protected TransportBase $transport;
    protected string $domainDefault;
    protected int $limitDay;

    public function __construct(int $projectId, TransportBase $transport, string $domainDefault = '', int $limitDay = 0)
    {
        $this->projectId = $projectId;
        $this->transport = $transport;
        $this->domainDefault = $domainDefault;
        $this->limitDay = $limitDay;
    }

    public function handler(): Transport
    {
        $model = new Transport();
        $this->transport->validate();
        $model->params = $this->transport->__toString();
        $model->project_id = $this->projectId;
        $model->status = Transport::STATUS_ON;
        $model->domain_id = $this->domainDefault ?
            (new Cqrs\Domain\GetDomain($this->domainDefault))->handler()->id : null;

        $model->limit_day = $this->limitDay;
        return $model->insert();
    }
}
