<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Transport;

use Xakki\Emailer\Exception;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;

class GetTransportByQueue
{
    protected Model\Queue $queue;

    public function __construct(Model\Queue $queue)
    {
        $this->queue = $queue;
    }

    public function handler(): Model\Transport
    {
        $domain = $this->queue->getEmail()->getDomain();
        $campaign = $this->queue->getCampaign();

        $row = Repository\Transport::find($this->queue->project_id, $domain->id, $campaign->transport_id);

        if (!$row) {
            throw new Exception\Exception('Not exist Transport');
        }
        return new Model\Transport($row);
    }
}
