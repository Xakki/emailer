<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Queue;

use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Model\Queue;
use Xakki\Emailer\Repository;

class ExecuteQueue extends AbstractQueue
{
    public function __construct(Emailer $emailer, array $skipIds = [], array $skipTransportIds = [])
    {
        $this->emailer = $emailer;
        $row = Repository\Queue::findOneByStatus(Queue::QUEUE_STATUS_NEW, true, $skipIds, $skipTransportIds);
        if (!$row) {
            $e = new DataNotFound('Not found data');
            $e->httpCode = 0;
            throw $e;
        }
        $this->queue = new Queue($row);
    }
}
